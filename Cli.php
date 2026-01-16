<?php

//declare(strict_types=1);

namespace cryodrift\qmemo;

use cryodrift\fw\Main;
use DateTime;
use cryodrift\qmemo\db\Old;
use cryodrift\qmemo\db\Repository;
use cryodrift\fw\cli\ParamFile;
use cryodrift\fw\Config;
use cryodrift\fw\Context;
use cryodrift\fw\Core;
use cryodrift\fw\FileCache;
use cryodrift\fw\HtmlUi;
use cryodrift\fw\interface\Handler;
use cryodrift\fw\interface\Installable;
use cryodrift\fw\tool\DbHelperStatic;
use cryodrift\fw\trait\CliHandler;

class Cli implements Handler, Installable
{
    use CliHandler;

    public function __construct(protected Repository $db, protected Config $config)
    {
    }

    public function handle(Context $ctx): Context
    {
        $ctx->response()->setStatusFinal();
        return $this->handleCli($ctx);
    }

    /**
     * @cli init search index tables
     * @cli param: -typ (fts,tok)
     */
    protected function initsearch(Context $ctx, string $typ = 'fts'): string
    {
        switch ($typ) {
            case 'fts':
                $this->db->ftsRecreate();
                return 'Done';
            case 'tok':
                $ts = (fn(): \cryodrift\tokenfts\Cli => Core::newObject(\cryodrift\tokenfts\Cli::class, $ctx))();
                $ts->droptables();
                $ts->migrate();
                $memos = $this->db->query("select * from memos where deleted!='y' or deleted is null", []);
                foreach ($memos as $memo) {
                    self::saveMemoFts($ctx, $ts, $memo);
                }
                return 'Done';
        }
        return '';
    }

    public static function saveMemoFts(Context $ctx, \cryodrift\tokenfts\Cli $ts, array $memo): void
    {
        $id = (string)$memo['id'];
        $content = Core::getValue('name', $memo) . ' ';
        $content .= Core::getValue('kat', $memo) . ' ';
        $content .= Core::getValue('keywords', $memo) . ' ';
        $content .= Core::getValue('title', $memo) . ' ';
        $content .= Core::getValue('linkname', $memo) . ' ';
        $name = $content;

        $content .= Core::getValue('content', $memo);
        if (empty($name)) {
            $name = substr($content, 0, 10);
        }
        Core::echo(__METHOD__, $name, $id, strlen($content));
        if ($content) {
            $text = new ParamFile($ctx, $name, $content, true);
            $ts->store($text, $id);
        }
    }

    /**
     * @cli files manager
     */
    protected function files(Context $ctx)
    {
        $ctx->request()->shiftArgs();
        return Core::newObject(\cryodrift\files\Cli::class, $ctx)->handle($ctx);
    }

    /**
     * @cli check integrity
     */
    protected function checkdb(Context $ctx)
    {
        $out = $this->db->getPdo()->exec('PRAGMA integrity_check;');
        $ctx->response()->setContent(Core::toLog($out));
        $this->db->vacuum();
        return $ctx;
    }

    /**
     * @cli delete the database files on disk
     * @cli params: -delete
     */
    protected function deletedbfiles(bool $delete = false): string
    {
        if ($delete) {
            $this->db->disconnect();
            foreach ($this->db->datafiles as $pathname) {
                $pathname = realpath($pathname);
                if (file_exists($pathname) && !unlink($pathname)) {
                    die('unlink failed: ' . $pathname);
                }
            }
        }
        return 'Done';
    }

    /**
     * @cli delete duplicates
     * @cli params: -delete
     */
    protected function deleteduplicates(bool $delete = false): string
    {
        if ($delete) {
            $this->db->runQueriesFromFile(Main::path('qmemo/db/d_duplicates.sql'));
        }
        return 'Done';
    }

    /**
     * @cli create schema
     * @cli params: [-s] (schema)
     * @cli params: [-i] (indexes)
     * @cli params: [-t] (triggers)
     * @cli params: [-a] (all)
     */
    protected function createdb(bool $a = false, bool $s = false, bool $i = false, bool $t = false): string
    {
        if ($a) {
            $s = $i = $t = true;
        }
        Core::echo(__METHOD__, $s, $i, $t);
        $out = '';
        if ($s) {
            $out .= $this->db->migrate();
        }
        if ($i) {
            $out .= $this->db->migrate('c_indexes.sql');;
        }
        if ($t) {
            $out .= $this->db->migrate('c_triggers.sql', '--END;');
        }
        return $out;
    }

    /**
     * @cli imports data from old format
     * @cli param: -path="path/to/db.sqlite"
     * @cli param: [-weeks=2]
     */
    protected function import(Context $ctx, string $path, int $weeks = 2)
    {
        $db = new Old('sqlite:' . $path);
        $this->db->triggerSet('update', false);
        $sql = Core::fileReadOnce('qmemo/db/s_oldmemos.sql');
        $tst = new DateTime()->modify('-' . $weeks . ' week')->getTimestamp();
        $rows = $db->query($sql, ['tst' => $tst]);
        $this->db->skipexisting = true;
        foreach ($rows as $row) {
            $this->db->runInsert($this->db::TABLE, $this->db::COLUMNS, $row);
        }

//        Core::echo(__METHOD__, $tst, Core::removeKeys(['content'], $rows));
        $this->db->triggerSet('update');
        return $ctx;
    }

    /**
     * @cli show memo
     * @cli param:id
     * @cli Example: show -id=1234
     *
     */
    protected function show(Context $ctx, string $id)
    {
        $out = $this->db->getById($id);
        $ctx->response()->setContent(Core::toLog('out: ', $out));
        return $ctx;
    }

    /**
     * @cli insert new memo
     * @cli params: params (id=10&name=hallo&)
     * @cli Example: insert -params="id=10&name=hallo&"
     *
     */
    protected function insert(Context $ctx, string $params)
    {
        parse_str($params, $data);
        $this->db->triggerSet('update', false);
        $out = $this->db->insert($data);
        $ctx->response()->setContent(Core::toLog($out));
        $this->db->triggerSet('update');
        return $ctx;
    }

    /**
     * @cli replace values in memo
     * @cli replace column searchstring replacestring
     * @cli TODO implement
     */
    protected function replace(Context $ctx)
    {
        return $ctx;
    }

    /**
     * @cli clear templates cache
     */
    protected function cc(Cache $cache): string
    {
        HtmlUi::cache(true);
        $cache->clear();
        return 'Cache cleared';
    }

    protected function test(Context $ctx): array
    {
        $out = [];

        return $out;
    }

    public function install(Context $ctx): array
    {
        $out = [];
        $out['database'] = $this->createdb(true, true, true);
        $out['search'] = $this->initsearch($ctx);

        return $out;
    }

    /**
     * @cli search memos
     * @cli -search="" (search string)
     */
    protected function searchmemo(string $search): array
    {
        return $this->db->getBySearch($search);
    }
}
