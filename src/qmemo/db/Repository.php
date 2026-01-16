<?php

//declare(strict_types=1);

namespace cryodrift\qmemo\db;

use PDO;
use cryodrift\qmemo\Cli;
use cryodrift\fw\Context;
use cryodrift\fw\Core;
use cryodrift\fw\trait\DbHelper;
use cryodrift\fw\trait\DbHelperCreate;
use cryodrift\fw\trait\DbHelperFnkText;
use cryodrift\fw\trait\DbHelperMigrate;
use cryodrift\fw\trait\DbHelperTrigger;

class Repository
{
    use DbHelper;
    use DbHelperFnkText;
    use DbHelperCreate;
    use DbHelperTrigger;
    use DbHelperMigrate;

    const string COLUMNS = 'id,type,aktiv,name,kat,keywords,title,linkname,content,contenttyp,status,deleted,changed,lang,created';
    protected string $mode = '';
    const string MODE_VERSIONS = 'versions';
    protected string $fts_query;
    protected string $filesfts_query;
    protected string $files_query;
    public array $datafiles = [];
    const string TABLE = 'memos';

    public function __construct(protected Context $ctx, string $storagedir)
    {
        $connectionstring = $storagedir . $ctx->user() . '/';
        $this->datafiles[] = $connectionstring . "memos.sqlite";
        $this->datafiles[] = $connectionstring . "files.sqlite";
        $this->filesfts_query = "ATTACH DATABASE '" . $connectionstring . "files_fts.sqlite' AS ftsfiles";
        $this->files_query = "ATTACH DATABASE '" . $connectionstring . "files.sqlite' AS files";
        $this->fts_query = "ATTACH DATABASE '" . $connectionstring . "memos_fts.sqlite' AS fts";
        $this->connect('sqlite:' . $connectionstring . 'memos.sqlite');
        $this->attachFunction('createShortContent');
    }

    public function setVersionsMode(string $mode = ''): void
    {
        $this->mode = $mode;
    }

    public function isVersionMode(): bool
    {
        return $this->mode === self::MODE_VERSIONS;
    }

    public function getById(string $id, bool $withdeleted = false): array
    {
        $sql = Core::fileReadOnce(__DIR__ . '/s_memo.sql');
        if ($this->isVersionMode()) {
            $sql = str_replace('main.memos ', 'main.memos_versions ', $sql);
        }
        $stmt = $this->pdo->prepare($sql);
        $deleted = $withdeleted ? 'y' : 'n';
        $stmt->bindValue(':withdeleted', $deleted);
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAll(int $page = 0, bool $withdeleted = false, int $limit = 20)
    {
        $sql = Core::fileReadOnce(__DIR__ . '/s_memos.sql');
        if ($this->isVersionMode()) {
            $sql = str_replace('main.memos ', 'main.memos_versions ', $sql);
        }
        $stmt = $this->pdo->prepare($sql);
        $deleted = $withdeleted ? 'y' : 'n';
        $stmt->bindValue(':withdeleted', $deleted);
        self::bindPage($stmt, $page, $limit);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBySearchNew(string $search, int $page = 0, bool $withdeleted = false, int $limit = 20): array
    {
        try {
            $ts = (fn(): \cryodrift\tokenfts\Cli => Core::newObject(\cryodrift\tokenfts\Cli::class, $this->ctx))();
            return $ts->search($search);
        } catch (\PDOException $ex) {
            Core::echo(__METHOD__, $ex, 'search: ', $search);
            return [];
        }
    }

    public function getBySearch(string $search, int $page = 0, bool $withdeleted = false, int $limit = 20): array
    {
        try {
            $sql = Core::fileReadOnce(__DIR__ . '/s_search.sql');
            if ($this->isVersionMode()) {
                $sql = str_replace('main.memos ', 'main.memos_versions ', $sql);
            }
            $this->ftsAttach();
            $stmt = $this->pdo->prepare($sql);
//            Core::echo(__METHOD__, 'search: ',$search);
//            $search = $this->escapeForFts($search);
            $deleted = $withdeleted ? 'y' : 'n';
            $stmt->bindValue(':withdeleted', $deleted);
            $stmt->bindValue(':query', $search);
            self::bindPage($stmt, $page, $limit);
            // INFO bei no such column errors sollte der suchende den string in anführungszeichen setzen
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $ex) {
            Core::echo(__METHOD__, $ex, 'search: ', $search);
            return [];
        }
    }


    public function virtualDelete(string $id)
    {
        if ($this->isVersionMode()) {
            throw new \Exception('No delete allowed on versioned items!', 666);
        }
        $sql = "UPDATE memos SET deleted = 'y' WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id);
        $this->ftsSave(['deleted' => 'y', 'id' => $id]);
        return $stmt->execute();
    }

    public function virtualUndelete(string $id)
    {
        if ($this->isVersionMode()) {
            throw new \Exception('No undelete allowed on versioned items!', 666);
        }
        $sql = "UPDATE memos SET deleted = null WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id);
        $this->ftsSave(['deleted' => null, 'id' => $id]);
        return $stmt->execute();
    }

    public function activateVersion(string $id)
    {
        if (!$this->isVersionMode()) {
            throw new \Exception('You are not in Version Mode!', 666);
        }

        $data = $this->getVersion($id);
        if ($data) {
            $data['id'] = $data['memos_id'];
            $this->triggerSet('update', false);
            $this->triggerSet('version', false);
            $this->runUpdate((string)$data['memos_id'], 'memos', explode(',', self::COLUMNS), $data);
            $this->triggerSet('update');
            $this->triggerSet('version');
        }
    }

    public function insert(array $data): string
    {
        $id = Core::getValue('id', $data);

        if ($id) {
            if (Core::getValue('deleted', $this->getById($id)) === 'y') {
                throw new \Exception('No insert or update allowed on deleted items!', 666);
            }
            if ($this->isVersionMode()) {
                throw new \Exception('No update allowed on Version items!', 666);
            }
        }

        $id = $this->runInsert('memos', self::COLUMNS, $data);

        $searchdata = $this->getById($id);
//        Core::echo(__METHOD__, $searchdata);
        $this->ftsSave(array_pop($searchdata));
        return $id;
    }

    public function ftsRecreate()
    {
        $this->ftsAttach();
        $this->pdo->exec("drop table IF EXISTS fts.memos_fts");
        $this->ftsCreate();
        $this->pdo->beginTransaction();
        $this->ftsPopulate();
        $this->pdo->commit();
    }


    private function ftsPopulate()
    {
        $this->ftsAttach();
        $cols = self::COLUMNS;
        $sql = "INSERT INTO fts.memos_fts (" . self::COLUMNS . ")
                SELECT " . $cols . " FROM memos";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
    }

    private function ftsCreate()
    {
        $this->ftsAttach();
        $this->pdo->exec(
          "CREATE VIRTUAL TABLE IF NOT EXISTS fts.memos_fts USING fts5(
            " . self::COLUMNS . ", content_rowid='id', tokenize='trigram')"
        );
    }

    /**
     *  this is much slower but needs less diskspace
     * TODO return memos not search result
     */
    public function saveToFtsNew(array $data)
    {
        $ts = Core::newObject(\cryodrift\tokenfts\Cli::class, $this->ctx);
        $ts->delete(Core::getValue('id', $data), 'memo_id');
        Cli::saveMemoFts($this->ctx, $ts, $data);
    }

    public function ftsSave(array $data)
    {
        $this->ftsAttach();
        $found = $this->ftsGetEntry((string)Core::getValue('id', $data));
        if ($found) {
            $this->runUpdate((string)Core::getValue('id', $found), 'fts.memos_fts', explode(',', self::COLUMNS), $data);
        } else {
            $this->runInsert('fts.memos_fts', self::COLUMNS, $data);
        }
    }

    public function ftsGetEntry(string $id): array
    {
        return Core::pop($this->query('select id from fts.memos_fts where id=:id', ['id' => $id]));
    }

    public function getVersions(string $memo_id, int $page = 0, int $limit = 20)
    {
        $sql = Core::fileReadOnce(__DIR__ . '/s_versions.sql');
        $stmt = $this->getStmt($sql);
        self::bindPage($stmt, $page, $limit);
        $stmt->bindValue(':id', $memo_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getVersion(string $version_id)
    {
        $sql = Core::fileReadOnce(__DIR__ . '/s_version.sql');
        $stmt = $this->getStmt($sql);
        $stmt->bindValue(':id', $version_id);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function getAllVersions(int $page = 0, int $limit = 20)
    {
        $sql = Core::fileReadOnce(__DIR__ . '/s_allversions.sql');
        $stmt = $this->getStmt($sql);
        self::bindPage($stmt, $page, $limit);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function addFile(string $memo_id, string $file_id)
    {
        $this->runInsert('memos_files', 'memo_id,file_id', ['memo_id' => $memo_id, 'file_id' => $file_id]);
    }

    public function remFile(string $memo_id, string $file_id)
    {
        $this->query('delete from memos_files where memo_id=:memo_id and file_id=:file_id', ['memo_id' => $memo_id, 'file_id' => $file_id]);
    }

    public function getFiles(string $memo_id, int $page = 0, bool $withdeleted = false, int $limit = 20)
    {
        $this->attachFiles();
        $sql = Core::fileReadOnce(__DIR__ . '/s_files.sql');
        $stmt = $this->getStmt($sql);
        $stmt->bindValue(':id', $memo_id);
        $stmt->bindValue(':withdeleted', $withdeleted ? 'y' : 'n');
        self::bindPage($stmt, $page, $limit);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAttachedFiles(int $page = 0, int $limit = 20): array
    {
        $this->attachFiles();
        $sql = Core::fileReadOnce(__DIR__ . '/s_attachedfiles.sql');
        $stmt = $this->getStmt($sql);
        self::bindPage($stmt, $page, $limit);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllFiles(string $memo_id, int $page = 0, bool $withdeleted = false, int $limit = 20): array
    {
        $this->attachFiles();
        $sql = Core::fileReadOnce(__DIR__ . '/s_allfiles.sql');
        $stmt = $this->getStmt($sql);

        $stmt->bindValue(':withdeleted', $withdeleted ? 'y' : 'n');
        $stmt->bindValue(':id', $memo_id);
        self::bindPage($stmt, $page, $limit);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFilesBySearch(string $search, int $page = 0, bool $withdeleted = false, int $limit = 20)
    {
        try {
            $this->attachFiles();
            $this->attachFilesFts();
            $sql = Core::fileReadOnce(__DIR__ . '/s_searchfiles.sql');
            if ($this->isVersionMode()) {
                $sql = str_replace('main.files ', 'main.files_versions ', $sql);
            }
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':withdeleted', $withdeleted ? 'y' : 'n');
            $stmt->bindValue(':query', $search);
            self::bindPage($stmt, $page, $limit);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $ex) {
            Core::echo(__METHOD__, $ex, 'search: ', $search);
            return [];
        }
    }

    public function addMemo(string $memo1_id, string $memo2_id)
    {
        if ($memo1_id !== $memo2_id) {
            $this->runInsert('memos_memos', 'memo1_id,memo2_id', ['memo1_id' => $memo1_id, 'memo2_id' => $memo2_id]);
        }
    }

    public function remMemo(string $memo1_id, string $memo2_id)
    {
        $this->query('delete from memos_memos where memo1_id=:memo1_id and memo2_id=:memo2_id', ['memo1_id' => $memo1_id, 'memo2_id' => $memo2_id]);
    }

    public function getAttachedMemos(string $memo_id, int $page = 0, int $limit = 20): array
    {
        $this->attachFiles();
        $sql = Core::fileReadOnce(__DIR__ . '/s_attachedmemos.sql');
        $stmt = $this->getStmt($sql);
        $stmt->bindValue(':memo_id', $memo_id);
        self::bindPage($stmt, $page, $limit);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function ftsAttach()
    {
        try {
            $this->pdo->exec($this->fts_query);
        } catch (\PDOException $ex) {
            // ignore alread attached database
//            Core::echo(__METHOD__, $ex->getMessage());
        }
    }

    public function attachFiles()
    {
        try {
            $this->pdo->exec($this->files_query);
        } catch (\PDOException $ex) {
            // ignore alread attached database
//            Core::echo(__METHOD__, $ex->getMessage());
        }
    }

    public function attachFilesFts()
    {
        try {
            $this->pdo->exec($this->filesfts_query);
        } catch (\PDOException $ex) {
            // ignore alread attached database
//            Core::echo(__METHOD__, $ex->getMessage());
        }
    }


}
