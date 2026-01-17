<?php

//declare(strict_types=1);

/**
 * @env USER_STORAGEDIRS=".cryodrift/users/"
 * @env QMEMO_TEMPLATECACHE=false
 * @env QMEMO_CACHEDIR=".cryodrift/cache/qmemo/"
 */

use cryodrift\fw\Core;

if (!isset($ctx)) {
    $ctx = Core::newContext(new \cryodrift\fw\Config());
}

$cfg = $ctx->config();

if (Core::env('USER_USEAUTH')) {
    \cryodrift\user\Auth::addConfigs($ctx, [
      'qmemo',
    ]);
}


$cfg[\cryodrift\qmemo\Cache::class] = ['cachedir' => Core::env('QMEMO_CACHEDIR')];
$cfg[\cryodrift\qmemo\Cli::class] = \cryodrift\qmemo\Web::class;
$cfg[\cryodrift\qmemo\Web::class] = [
  'route' => '/qmemo',
  'templatepath' => __DIR__ . '/ui/base/main.html',
  'templatedir_shared' => \cryodrift\fw\Main::$rootdir . 'shared/ui/',
  'storagedir' => Core::env('USER_STORAGEDIRS'),
  'title' => 'Memos',
  'description' => 'Personal Memo',
  'langcode' => 'de',
  'db' => \cryodrift\qmemo\db\Repository::class,
  'getvar_uistate' => ['insert', 'editor', 'theme', 'files_menu', 'memos_menu', 'memos_filter', 'tabs', 'uploaded_dir', 'uploaded_search'],
  'getvar_defaults' => [
    'memo_id' => '',
    'memos_page' => 0,
    'memos_search' => '',
    'memos_menu' => '',
    'memos_filter' => [],
    'file_id' => '',
    'files_page' => 0,
    'files_search' => '',
    'files_menu' => '',
    'insert' => '',
    'editor' => '',
    'theme' => '',
    'tabs' => '',
  ],
  'componenthandler' => \cryodrift\qmemo\Web::class,
  'components' => [
    'insert',
    'memos_search',
    'memos_menu',
    'memos_filter',
    'memos',
    \cryodrift\fw\Config::container('files_menu', \cryodrift\files\Web::class, 'modefilter'),
    \cryodrift\fw\Config::container('files_search', \cryodrift\files\Web::class, 'search'),
    \cryodrift\fw\Config::container('quicklinks', \cryodrift\quicklinks\Web::class, 'show'),
    \cryodrift\fw\Config::container('files_dirlist', \cryodrift\files\Web::class, 'dirs'),
    'files',
    'uploaded',
    'uploaded_search',
    'memo',
  ],
  'insert_required' => ['name'],
  'cache_templates' => Core::env('QMEMO_TEMPLATECACHE')
];
$cfg[\cryodrift\qmemo\db\Repository::class] = [
  'storagedir' => Core::env('USER_STORAGEDIRS'),
];
$cfg[\cryodrift\files\db\Repository::class] = [
  'storagedir' => Core::env('USER_STORAGEDIRS'),
];

\cryodrift\fw\Router::addConfigs($ctx, [
  'qmemo/cli' => \cryodrift\qmemo\Cli::class,
], \cryodrift\fw\Router::TYP_CLI);

\cryodrift\fw\Router::addConfigs($ctx, [
  'qmemo' => \cryodrift\qmemo\Web::class,
  'qmemo/api' => \cryodrift\qmemo\Web::class,
  'qmemo/file' => \cryodrift\files\Web::class,
  'files/file' => \cryodrift\files\Web::class,
  'qmemo/api/files_delete' => [[\cryodrift\files\Api::class, 'delete', 'none', '/memo']],
  'qmemo/api/files_undelete' => [[\cryodrift\files\Api::class, 'undelete', 'none', '/memo']],
  'qmemo/api/fileviewer' => [[\cryodrift\files\Web::class, 'fileviewer', 'none', '/memo']],
  'qmemo/api/files_menu' => [[\cryodrift\files\Web::class, 'modefilter', 'none', '/memo']],
  'qmemo/api/files_search' => [[\cryodrift\files\Web::class, 'search', 'none', '/memo']],
  'qmemo/api/files_dirlist' => [[\cryodrift\files\Web::class, 'dirs', 'none', '/memo']],
  'ce-plugins' => [[\cryodrift\fw\FileHandler::class, 'folder', ['assetdir' => 'qmemo/ui/editor/ce/plugins']]],
], \cryodrift\fw\Router::TYP_WEB);

\cryodrift\fw\FileHandler::addConfigs($ctx, [
  'ce.js' => 'qmemo/ui/editor/ce/ce.js',
  'editor.js' => 'qmemo/ui/editor/editor.js',
  'memoinsertobserver.js' => 'qmemo/ui/insert/observer.js'
]);

