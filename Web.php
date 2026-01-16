<?php

//declare(strict_types=1);

namespace cryodrift\qmemo;

use cryodrift\qmemo\db\Repository;
use cryodrift\shared\ui\search\Cmp as SearchComponent;
use cryodrift\fw\Config;
use cryodrift\fw\Context;
use cryodrift\fw\Core;
use cryodrift\fw\HtmlUi;
use cryodrift\fw\interface\Handler;
use cryodrift\fw\trait\ComponentHelper;
use cryodrift\fw\trait\PageHandler;
use cryodrift\fw\trait\WebHandler;

class Web implements Handler
{
    use WebHandler;
    use PageHandler;
    use ComponentHelper;

    protected Context $ctx;
    protected string $rootdir;

    public function __construct(protected Repository $db, string $storagedir, protected Config $config, Context $ctx, protected \cryodrift\uploader\Api $uploader, protected \cryodrift\files\Web $files, protected string $templatedir_shared)
    {
        $files->templatedir = __DIR__;
        $files->templatedir_shared = $templatedir_shared;
        $this->rootdir = $storagedir . $ctx->user() . '/uploads/';
        $this->outHelperAttributes([
          'ROUTE' => '/memo',
          'PATH' => '/' . $ctx->request()->path()->getString()
        ]);
    }


    public function handle(Context $ctx): Context
    {
        HtmlUi::setUistate(Core::getValue('getvar_uistate', $this->config, []));
        if (Core::getValue('cache_templates', $this->config, false)) {
            HtmlUi::cache();
            $ctx->response()->addAfterRunner(fn() => HtmlUi::cache());
        }
        $this->db->setVersionsMode($ctx->request()->vars('memos_menu'));
        $ctx->request()->setDefaultVars(Core::getValue('getvar_defaults', $this->config, []));
        $this->methodname = 'command';
        $this->commandname = 'method';
        return $this->handleWeb($ctx);
    }

    /**
     * @web render the Page
     */
    public function index(Context $ctx): Context
    {
        $this->db->setVersionsMode($ctx->request()->vars('memos_menu', ''));
        $ctx = $this->handlePage($ctx, $this->config);
        $ui = $ctx->response()->getContent();
        $comps = $this->componentHelper($ctx, $this->config);
        $ui->setAttributes(['memo_id' => $ctx->request()->vars('memo_id', '')]);
        $ui->setAttributes($comps, false, false);
        return $ctx;
    }


    /**
     * @web  shows one memo
     */
    protected function memo(Context $ctx): HtmlUi
    {
        $memo_id = $ctx->request()->vars('memo_id');
        $memos_menu = $ctx->request()->vars('memos_menu');
        $withdeleted = $memos_menu === 'deleted';
        $data = $this->db->getById($memo_id, $withdeleted);
        $data = HtmlUi::addQuery($ctx, $data, ['id' => 'memo_id'], ['memo_id']);
        switch ($memos_menu) {
            case'deleted':
                $mode = ['apiname' => 'memo_undelete', 'modevalue' => 'Undelete', 'topbuttons' => []];
                break;
            case $this->db::MODE_VERSIONS:
                $mode = ['apiname' => 'memo_activate', 'modevalue' => 'Activate', 'topbuttons' => []];
                break;
            default:
                $query = HtmlUi::addQuery($ctx, [], ['id' => 'memo_id'], ['memo_id']);
                $mode = [
                  'apiname' => 'memo_delete',
                  'modevalue' => 'Delete'

                ];
                $mode['topbuttons'][] = HtmlUi::fromFile('qmemo/ui/memo/btn_attached.html')->setAttributes($query);
                $mode['topbuttons'][] = HtmlUi::fromFile('qmemo/ui/memo/btn_edit.html')->setAttributes($query);
                $ctx->request()->setVar('memos_menu', $this->db::MODE_VERSIONS);
                $query = HtmlUi::addQuery($ctx, [], ['id' => 'memo_id'], ['memo_id']);
                $mode['topbuttons'][] = HtmlUi::fromFile('qmemo/ui/memo/btn_versions.html')->setAttributes($query);

                $ctx->request()->setVar('memos_menu', '');
        }
        $excludes = [];
        $data = Core::addData($data, function ($v) use (&$excludes) {
            if (Core::getValue('contenttyp', $v) === 'html') {
                $v['content'] = html_entity_decode($v['content']);
                $excludes = ['content'];
            }
            return $v;
        });

        return HtmlUi::fromFile('qmemo/ui/memo/_.html', 'memo')->fromBlock('memo', true)->setAttributes(['memo' => $data, ...$mode], false, true, $excludes);
    }

    /**
     * @web  shows a limited list of memos
     */
    protected function memos(Context $ctx): HtmlUi
    {
        $memos_page = $ctx->request()->vars('memos_page', 0);
        $memos_search = $ctx->request()->vars('memos_search');
        $memos_menu = $ctx->request()->vars('memos_menu');
        $memo_id = $ctx->request()->vars('memo_id');
        switch ($memos_menu) {
            case $this->db::MODE_VERSIONS:
                $data = $this->db->getAllVersions($memos_page);
                break;
            default:
                if ($memos_search) {
                    $data = $this->db->getBySearch($memos_search, $memos_page, $memos_menu === 'deleted');
                } else {
                    $data = $this->db->getAll($memos_page, $memos_menu === 'deleted');
                }
        }
        return $this->render_memos($ctx, $data, $memo_id, $memos_menu);
    }

    private function render_memos(Context $ctx, array $data, string $memo_id, string $memos_menu): HtmlUi
    {
        // add rem memo from memo show buttons here
        if ($memo_id) {
            $ids = explode(',', Core::getValue('memoids', Core::pop($this->db->getById($memo_id)), '', true));
        } else {
            $ids = [];
        }

        foreach ($data as &$value) {
            $id = Core::getValue('id', $value);
            if ($memo_id && $memo_id != $id && ($memos_menu === 'normal' || $memos_menu === '')) {
                if (in_array($id, $ids)) {
                    $value['memo_add_rem_btn'] = $this->get_memo_rem_btn($ctx, ['id' => $id]);
                } else {
                    $value['memo_add_rem_btn'] = $this->get_memo_add_btn($ctx, ['id' => $id]);
                }
            } else {
                $value['memo_add_rem_btn'] = '';
            }
        }

        if (count($data)) {
            $data = HtmlUi::addQuery($ctx, $data, ['id' => 'memo_id'], ['memo_id', 'memos_page', 'memos_search', 'memos_menu']);
            return HtmlUi::fromFile('qmemo/ui/memos/memos.html', 'memos')->fromBlock('memos')->setAttributes(['memos_block' => $data, ...$ctx->request()->vars()]);
        } else {
            return new HtmlUi();
        }
    }

    /**
     * @web  Show little dropdown menu
     */
    protected function memos_menu(Context $ctx): HtmlUi
    {
        $data = [
          ['value' => 'Normal', 'name' => 'normal'],
          ['value' => 'Deleted', 'name' => 'deleted'],
          ['value' => 'Versions', 'name' => $this->db::MODE_VERSIONS]
        ];
        $memos_menu = $ctx->request()->vars('memos_menu', 'normal', true);
        $memos_menu = array_reduce($data, fn($carry, $value) => $carry .= Core::getValue('name', $value) === $memos_menu ? Core::getValue('value', $value) : '');
        $data = HtmlUi::addQuery($ctx, $data, ['name' => 'memos_menu'], ['memo_id', 'memos_menu']);
        $data = HtmlUi::makeActive($data, $ctx->request()->vars('memos_menu'), 'name');
//        Core::echo(__METHOD__, $data);
        return HtmlUi::fromFile('qmemo/ui/shared/menu.html', 'memos_menu')->fromBlock('memos_menu')->setAttributes([
            'data-loader' => 'memos_menu||outer memos files memos_search|parent_search insert|insert_container',
            'id' => 'memos_menu',
            'selected' => $memos_menu,
            'list' => $data
          ]
        );
    }

    /**
     * @web  Filters Menu
     */
    protected function memos_filter(Context $ctx): HtmlUi
    {
        $state = $ctx->request()->vars('memos_filter', [], true);
        //TODO this line is too important lets check parameter content in a central place
        $state = array_filter($state, fn($a) => in_array($a, ['files', 'memos']));

        $data = [
          ['value' => 'Files', 'name' => 'files', 'q' => $this->getMultiParamState('files', $state, ['memos'])],
          ['value' => 'Memos', 'name' => 'memos', 'q' => $this->getMultiParamState('memos', $state, ['files'])]
        ];


        $data = HtmlUi::addQuery($ctx, $data, ['q' => 'memos_filter'], ['memo_id']);
        $data = HtmlUi::makeSelected($data, $state, 'name', 'g-active');
        return HtmlUi::fromFile('qmemo/ui/shared/menu.html', 'block')->fromBlock('block')
          ->setAttributes([
              'data-loader' => 'memos_filter||outer memos memos_search|memos_search insert|insert_container',
              'id' => 'memos_filter',
              'selected' => implode('|', $state),
              'list' => $data
            ]
          );
    }

    private function getMultiParamState(string $current, array $state, array $names): array
    {
        $out = [];
        foreach ($names as $name) {
            if (in_array($name, $state)) {
                $out[] = $name;
            }
        }
        if (!in_array($current, $state)) {
            $out[] = $current;
        }
        return $out;
    }

    /**
     * @web  delete a memo
     */
    protected function memo_delete(Context $ctx): HtmlUi
    {
        if ($ctx->request()->isPost()) {
            $memo_id = $ctx->request()->vars('memo_id');
            if ($memo_id) {
                $this->db->virtualDelete($memo_id);
            }
        }
        return new HtmlUi();
    }

    /**
     * @web  undelete a memo
     */
    protected function memo_undelete(Context $ctx): HtmlUi
    {
        if ($ctx->request()->isPost()) {
            $memo_id = $ctx->request()->vars('memo_id');
            if ($memo_id) {
                $this->db->virtualUndelete($memo_id);
            }
        }
        return new HtmlUi();
    }

    /**
     * @web  activate a version
     */
    protected function memo_activate(Context $ctx): HtmlUi
    {
        if ($ctx->request()->isPost()) {
            $memo_id = $ctx->request()->vars('memo_id');
            if ($memo_id) {
                $this->db->activateVersion($memo_id);
            }
        }
        return new HtmlUi();
    }


    /**
     * @web  handles inserts
     */
    protected function insert(Context $ctx): HtmlUi
    {
        $memos_menu = $ctx->request()->vars('memos_menu');
        $alert = HtmlUi::fromFile('qmemo/ui/shared/alert_fadeout.html');
        $required = Core::getValue('insert_required', $this->config, []);
        $id = $ctx->request()->vars('memo_id');
        if ($ctx->request()->isPost()) {
            if ($ctx->request()->vars('submitbutton') === 'create') {
                $ctx->request()->remVar('id');
            }
//            Core::echo(__METHOD__, $ctx->request()->vars(), $_POST, $_REQUEST);
            try {
                if ($ctx->request()->hasVars($required)) {
                    $this->db->triggerSet('update');
                    $olddata = $this->db->getById($ctx->request()->vars('id'));
                    $id = $this->db->insert($ctx->request()->vars());
                    if ($olddata) {
                        $alert->setAttributes(['type' => 'success', 'text' => 'Updated on id ' . $id]);
                    } else {
                        $alert->setAttributes(['type' => 'success', 'text' => 'Inserted to new id ' . $id]);
                    }
                } else {
                    $alert->setAttributes(['type' => 'error', 'text' => 'Missing required data for: ' . implode(' ', $required)]);
                }
            } catch (\Exception $ex) {
                if ($ex->getCode() === 666) {
                    $alert->setAttributes(['type' => 'error', 'text' => $ex->getMessage()]);
                } else {
                    echo Core::toLog(__METHOD__, $ex->getMessage());
                }
            }
        }
        $contenttyp = ['contenttyphtml' => '', 'contenttyptext' => ''];
        if ($id) {
            $ctx->request()->setVar('id', $id);
            $dbdata = $this->db->getById($id, $memos_menu === 'deleted');
            $dbdata = Core::pop($dbdata);
            if ($dbdata) {
                $contenttyp['contenttyphtml'] = $dbdata['contenttyp'] === 'html' ? 'selected' : '';
                $contenttyp['contenttyptext'] = $dbdata['contenttyp'] === 'text' ? 'selected' : '';
                $data = ['query' => '', ...$contenttyp, ...$dbdata, ...$ctx->request()->vars()];
            }
        } else {
            $data = ['query' => '', ...$contenttyp, 'id' => '', 'name' => '', 'kat' => '', 'content' => '', ...$ctx->request()->vars()];
        }
        if ($alert->getAttributes()) {
            $data['alerts'] = [$alert];
        } else {
            $data['alerts'] = [];
        }
        $fields = $this->insert_fields($data, $required);
        $data = ['insert' => $data];
        $editor = Core::extractData($this->insert_text($ctx)->getAttributes(), ['data-loader', 'buttontext']);
        $data = HtmlUi::addQuery($ctx, $data, ['id' => 'memo_id'], ['memo_id']);
        return HtmlUi::fromFile('qmemo/ui/insert/insert.html', 'insert')->fromBlock('insert', true)
          ->setAttributes(['insert' => $data])
          ->setAttributes(['insert_box_fields' => $fields, ...$editor], false, true, ['insert_box_fields'])
          ->setAttributes(['classes' => 'g-h']);
    }


    /**
     * @return HtmlUi|null
     */
    private function insert_fields(array $data, array $required): HtmlUi
    {
        $fields = HtmlUi::fromString('{{@}}block{{@}}{{@data@}}{{@}}block{{@}}');
        $templates = [];

        foreach (['name', 'title', 'kat', 'keywords', 'contenttyp'] as $key) {
            $value = Core::getValue($key, $data);
            switch ($key) {
                case in_array($key, ['contenttyp']):
                    $tpl = HtmlUi::fromFile('qmemo/ui/shared/form/select.html');
                    $tpl->setAttributes([
                        'name' => $key,
                        'id' => 'insert_',
                        'required' => in_array($key, $required) ? 'required' : '',
                        'label' => ucfirst($key)
                      ]
                    );
                    $tpl->setAttributes([
                      'options' => [
                        ['value' => '', 'text' => '-', 'selected' => !$value ? 'selected' : ''],
                        ['value' => 'html', 'text' => 'html', 'selected' => $value === 'html' ? 'selected' : ''],
                        ['value' => 'text', 'text' => 'text', 'selected' => $value === 'text' ? 'selected' : ''],
                      ]
                    ]);
                    $templates[] = $tpl;
                    break;
                case in_array($key, ['name', 'title', 'kat', 'keywords']):
                    $tpl = HtmlUi::fromFile('qmemo/ui/shared/form/text.html');
                    $tpl->setAttributes([
                        'name' => $key,
                        'id' => 'insert_',
                        'value' => $value,
                        'required' => in_array($key, $required) ? 'required' : '',
                        'label' => ucfirst($key)
                      ]
                    );
                    $templates[] = $tpl;
                    break;
            }
        }
        $fields->setAttributes(['block' => $templates]);

        return $fields;
    }

    /**
     * @web  contact search form HtmlUi
     */
    protected function memos_search(Context $ctx): HtmlUi
    {
        if (!$this->db->isVersionMode()) {
            $quickmenu = ['kat:accounts', 'kat:test', 'name:test', 'name:account -'];
            return new SearchComponent($ctx, 'memos_search', 'memos', ['memos_search'], array_map(fn($a) => $a, $quickmenu));
        } else {
            return new HtmlUi();
        }
    }

    /**
     * @web  show wysiwyg editor
     */
    protected function insert_html(Context $ctx): HtmlUi
    {
        $memo = $this->memo($ctx);
        $data = $memo->getAttributes();
        $data = Core::getValue('memo', $data, []);
        $data = Core::pop($data);
//        Core::echo(__METHOD__, $data);
        if ($data) {
            $data['content'] = Core::cleanData(Core::getValue('content', $data));
            return HtmlUi::fromFile('qmemo/ui/editor/editor.html')
              ->setAttributes($data, true, false)
              ->setAttributes(['classes' => 'g-h2']);
        } else {
            return new HtmlUi();
        }
    }

    /**
     * @web  show textarea editor
     */
    protected function insert_text(Context $ctx): HtmlUi
    {
        $memo = $this->memo($ctx);
        $data = $memo->getAttributes();
        $data = Core::pop($data['memo']);
        if ($data) {
            return HtmlUi::fromFile('qmemo/ui/insert/content.html')
              ->setAttributes($data, true, false)
              ->setAttributes(['classes' => 'g-h']);
        } else {
            return new HtmlUi();
        }
    }

    /**
     * @web  get list of versions for memo
     */
    protected function versions(Context $ctx): HtmlUi
    {
        $memos_page = $ctx->request()->vars('memos_page', 0);
        $memos_menu = $ctx->request()->vars('memos_menu');
        $memo_id = $ctx->request()->vars('memo_id');
        $data = $this->db->getVersions($memo_id, $memos_page);
        return $this->render_memos($ctx, $data, $memo_id, $memos_menu);
    }

    /**
     * @web  get single memo list item
     */
    protected function memo_item(Context $ctx): HtmlUi
    {
        $memo_id = $ctx->request()->vars('memo_id');
        $data = $this->db->getById($memo_id);
        $memos_menu = $ctx->request()->vars('memos_menu');
        $memos = $this->render_memos($ctx, $data, $memo_id, $memos_menu);

        return $memos->fromBlock('memos_block', true)
          ->setAttributes(['memos_block' => $data])
          ->setAttributes($memos->getAttributes());
    }

    /**
     * @web  get single memo content
     */
    protected function memo_content(Context $ctx): HtmlUi
    {
        $memo_id = $ctx->request()->vars('memo_id');

        $data = $this->db->getById($memo_id);
        if (count($data)) {
            $data = HtmlUi::addQuery($ctx, $data, ['id' => 'memo_id'], ['memo_id']);

            $data = Core::pop($data);
            if (Core::getValue('contenttyp', $data) == 'html') {
                $tagname = 'div';
                $enc = false;
            } else {
                $tagname = 'pre';
                $enc = true;
            }
            return HtmlUi::fromFile('qmemo/ui/memo/content.html')
              ->setAttributes($data, false, $enc)
              ->setAttributes(['tagname' => $tagname]);
        } else {
            return new HtmlUi();
        }
    }


    /**
     * @web  get list of files attached to memo
     */
    protected function memo_memos(Context $ctx): HtmlUi
    {
        $memo_id = $ctx->request()->vars('memo_id');
        $memos_page = $ctx->request()->vars('memos_page', 0);
        $data = $this->db->getAttachedMemos($memo_id, $memos_page);
        return $this->render_memos($ctx, $data, $memo_id, '');
    }

    /**
     * @web  rem memo from memo
     */
    protected function memo_rem(Context $ctx): HtmlUi
    {
        $memo2_id = $ctx->request()->vars('memo2_id');
        $memo_id = $ctx->request()->vars('memo_id');
        $this->db->remMemo($memo_id, $memo2_id);
        return $this->get_memo_add_btn($ctx, ['id' => $memo2_id]);
    }

    /**
     * @web  add memo to memo
     */
    protected function memo_add(Context $ctx): HtmlUi
    {
        $memo2_id = $ctx->request()->vars('memo2_id');
        $memo_id = $ctx->request()->vars('memo_id');
        $this->db->addMemo($memo_id, $memo2_id);
        return $this->get_memo_rem_btn($ctx, ['id' => $memo2_id]);
    }

    /**
     * @web  add file to memo
     */
    protected function file_add(Context $ctx): HtmlUi
    {
        $file_id = $ctx->request()->vars('file_id');
        $memo_id = $ctx->request()->vars('memo_id');
        $this->db->addFile($memo_id, $file_id);
        return $this->get_file_rem_btn($ctx, ['id' => $file_id]);
    }

    /**
     * @web  rem file from memo
     */
    protected function file_rem(Context $ctx): HtmlUi
    {
        $file_id = $ctx->request()->vars('file_id');
        $memo_id = $ctx->request()->vars('memo_id');
        $this->db->remFile($memo_id, $file_id);
        return $this->get_file_add_btn($ctx, ['id' => $file_id]);
    }

    /**
     * @web  get list of files attached to memo
     */
    protected function memo_files(Context $ctx): HtmlUi
    {
        $memo_id = $ctx->request()->vars('memo_id');
        $data = $this->db->getFiles($memo_id);
        if (count($data)) {
//            Core::echo(__METHOD__, $memo_id);
            $data = $this->attach_file_btns($ctx, $data);
            return $this->files->render_files($ctx, $data);
        } else {
            return new HtmlUi();
        }
    }

    /**
     * @web  files listing
     */
    protected function files(Context $ctx): HtmlUi
    {
        $files_page = $ctx->request()->vars('files_page', 0);
        $files_search = $ctx->request()->vars('files_search');
        $files_menu = $ctx->request()->vars('files_menu');
        $memo_id = $ctx->request()->vars('memo_id');
        switch ($files_menu) {
            case 'attached':
                $data = $this->db->getAttachedFiles($files_page);
                break;
            default:
                if ($files_search) {
                    $data = $this->db->getFilesBySearch($files_search, $files_page, $files_menu === 'deleted');
                } else {
                    $data = $this->db->getAllFiles($memo_id, $files_page, $files_menu === 'deleted');
                }
        }
        if (count($data)) {
            $data = $this->attach_file_btns($ctx, $data);
            return $this->files->render_files($ctx, $data);
        } else {
            return new HtmlUi();
        }
    }

    private function get_memo_add_btn(Context $ctx, array $data): HtmlUi
    {
        $data = HtmlUi::addQuery($ctx, $data, ['id' => 'memo2_id'], ['memo2_id', 'memo_id', 'files_page', 'files_search', 'files_menu']);
        return HtmlUi::fromFile('qmemo/ui/shared/btn_add.html')->setAttributes($data)->setAttributes($ctx->request()->vars())->setAttributes(['api' => 'memo_add']);
    }

    private function get_memo_rem_btn(Context $ctx, array $data): HtmlUi
    {
        $data = HtmlUi::addQuery($ctx, $data, ['id' => 'memo2_id'], ['memo2_id', 'memo_id', 'files_page', 'files_search', 'files_menu']);
        return HtmlUi::fromFile('qmemo/ui/shared/btn_rem.html')->setAttributes($data)->setAttributes($ctx->request()->vars())->setAttributes(['api' => 'memo_rem']);
    }

    public function get_file_rem_btn(Context $ctx, array $data): HtmlUi
    {
        $data = HtmlUi::addQuery($ctx, $data, ['id' => 'file_id'], ['file_id', 'memo_id', 'files_page', 'files_search', 'files_menu']);
        return HtmlUi::fromFile('qmemo/ui/shared/btn_rem.html')->setAttributes($data)->setAttributes($ctx->request()->vars())->setAttributes(['api' => 'file_rem']);
    }

    public function get_file_add_btn(Context $ctx, array $data): HtmlUi
    {
        $data = HtmlUi::addQuery($ctx, $data, ['id' => 'file_id'], ['file_id', 'memo_id', 'files_page', 'files_search', 'files_menu']);
        return HtmlUi::fromFile('qmemo/ui/shared/btn_add.html')->setAttributes($data)->setAttributes($ctx->request()->vars())->setAttributes(['api' => 'file_add']);
    }

    private function attach_file_btns(Context $ctx, array $data): array
    {
        return Core::addData($data, function ($v) use ($ctx) {
            $v['file_add_rem_btn'] = '';

            $menu = $ctx->request()->vars('memos_menu');
            if ($ctx->request()->vars('memo_id') && ($menu === 'normal' || $menu === '')) {
                switch (Core::getValue('file_has_memo', $v, 'NA')) {
                    case 1:
                        $v['file_add_rem_btn'] = $this->get_file_rem_btn($ctx, ['id' => $v['id']]);
                        break;
                    case 0:
                        $v['file_add_rem_btn'] = $this->get_file_add_btn($ctx, ['id' => $v['id']]);
                        break;
                }
            }
            $links = HtmlUi::fromString();
            if (Core::getValue('memoids', $v)) {
                $ids = explode(',', Core::getValue('memoids', $v));
                $memos = explode("\n", Core::getValue('memotext', $v, '', true));
                foreach ($ids as $key => $id) {
                    $tmp = [];
                    $tmp['id'] = $v['id'];
                    $tmp['memo_id'] = $id;
                    $tmp['memo_text'] = $memos[$key];
                    $memo = HtmlUi::fromFile('qmemo/ui/files/btn_memo.html')
                      ->setAttributes(HtmlUi::addQuery($ctx, $tmp, ['id' => 'file_id', 'memo_id' => 'memo_id'], ['file_id', 'memo_id', 'files_menu']));
                    $links->setAttributes([$memo]);
                }
            }
            $v['links'] = $links;
            $v['btn_select'] = HtmlUi::fromFile('qmemo/ui/uploads/btn_select.html')->setAttributes(['id' => $v['uid']]);
            return $v;
        });
    }

}
