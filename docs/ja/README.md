# Upgrade ドキュメント

Upgrade プラグインは `upgrade` コンソールシェルを提供します。このシェルは、CakePHP 1.3 アプリケーションを
CakePHP 2 にアップグレードするために必要な機械的な変更の大部分を自動化します。
[pieceofcake2/cakephp](https://github.com/pieceofcake2/cakephp) で動作します。

- [インストール](#installation)
- [シェルの実行](#running-the-shell)
- [コマンド](#commands)
- [オプション](#options)
- [アップグレードの後で](#after-the-upgrade)

> [!IMPORTANT]
> このシェルはファイルを移動し、書き換えます。実行する前にアプリケーションをバックアップするか Git にコミットし、
> まず `--dry-run` で試してください。

<a id="installation"></a>

## インストール

[Composer](https://getcomposer.org/) で、開発用の依存パッケージとしてプラグインをインストールします。

```bash
composer require --dev pieceofcake2/upgrade
```

このプラグインは `cakephp-plugin` タイプなので、`composer/installers` によってプラグインのディレクトリに
インストールされます。正しい場所にインストールされるように、アプリケーションの `composer.json` で
`installer-paths` を設定してください。

| 構成 | `installer-paths` |
| --- | --- |
| モダン (`config/`、`src/`、`templates/`、`plugins/`) | `"plugins/{$name}/": ["type:cakephp-plugin"]` |
| レガシー (`app/Config/`、`app/Plugin/`) | `"app/Plugin/{$name}/": ["type:cakephp-plugin"]` |

プラグインは `config/bootstrap.php` (レガシー構成では `app/Config/bootstrap.php`) で読み込みます。
すでに `CakePlugin::loadAll()` を使っている場合は必要ありません。

```php
CakePlugin::load('Upgrade');
```

<a id="running-the-shell"></a>

## シェルの実行

`bin/cake` (レガシー構成では `app/Console/cake`) でシェルを実行します。

```bash
# Run all upgrade commands
bin/cake upgrade all

# See what would be changed without changing anything
bin/cake upgrade all --dry-run

# Run a single command
bin/cake upgrade locations
```

アップグレードするアプリケーションのディレクトリで、そのアプリケーションをコンソールのアプリケーション (`APP`) として
実行してください。`locations` はカレントディレクトリを基準にフォルダを移動し、その他のコマンドはアプリケーション
(または `--plugin` で指定したプラグイン) の PHP ファイルを書き換えます。

<a id="commands"></a>

## コマンド

| コマンド | 説明 |
| --- | --- |
| `all` | 他のすべてのコマンドを実行します。 |
| `tests` | テストクラスの名前を `FooTestCase` から `FooTest` に変更します。 |
| `locations` | ファイルとフォルダを CakePHP 2 での場所に移動します (`config` を `Config` に、`models` を `Model` に、`views` を `View` に、`tests/cases` を `Test/Case` に、など)。`plugins/` にあるプラグインも対象です。 |
| `i18n` | 翻訳関数の呼び出しを更新します。`__('text', true)` は `__('text')` になり、直接出力していた呼び出しには `echo` が付きます。 |
| `helpers` | ビューでのヘルパーの呼び出しを `$this->Helper` に更新します。 |
| `basics` | `basics.php` の削除された関数を PHP のネイティブ関数に置き換えます。 |
| `request` | 削除されたリクエストのプロパティ (`$this->data`、`$this->params`、...) を `$this->request` に置き換えます。 |
| `configure` | `Configure::read()` を `Configure::read('debug')` に置き換えます。 |
| `constants` | `CONFIGS` や `CONTROLLERS` などの廃止された定数を、`APP` から組み立てたパスに置き換えます。 |
| `controller_redirects` | コントローラーの `$this->redirect()` の呼び出しに `return` を追加します。 |
| `components` | コンポーネントが `Component` クラスを継承するようにします。 |
| `exceptions` | `cakeError()` の呼び出しを例外に置き換えます。 |

<a id="options"></a>

## オプション

| オプション | 説明 |
| --- | --- |
| `--plugin`、`-p` | 指定したプラグインだけを更新します。 |
| `--ext`、`-e` | 更新するファイルの拡張子。パイプ区切りのリストか、`preg_match()` 互換のサブパターンで指定します (デフォルト: `php\|ctp\|thtml\|inc\|tpl`)。 |
| `--git`、`-g` | Git が履歴を保持するように、`git mv` でファイルを移動します。カレントディレクトリは Git リポジトリである必要があります。 |
| `--dry-run`、`-d` | ファイルを変更せずに変更内容を表示します。 |

<a id="after-the-upgrade"></a>

## アップグレードの後で

このシェルは、`app/Config/`、`app/Controller/`、`app/View/` などを使う CakePHP 2 のディレクトリ構成を作成します。
変更内容を確認し、テストを実行して、シェルが自動的に変換できなかった部分を修正してください。

pieceofcake2/cakephp は、CakePHP 5 スタイルの構成 (`config/`、`src/`、`templates/`) もサポートしています。
アップグレードしたアプリケーションをこの構成に移すには、[pieceofcake2/app](https://github.com/pieceofcake2/app)
スケルトンのファイルの移行対応表に従ってください。

<a id="origin"></a>

## 由来

このパッケージは [cakephp/cakephp](https://github.com/cakephp/cakephp) (Packagist では
`cakephp/cakephp`、アップストリームの最終バージョンは 2.10.24) の `upgrade` コンソールシェルをもとに別のパッケージとして切り出したもので、
元の作者は CakePHP コミュニティ (Cake Software Foundation, Inc.) です。
[pieceofcake2](https://github.com/pieceofcake2) が、モダンな PHP で動く CakePHP 2 向けに
メンテナンスしています。オリジナルからの変更点は
[コミット履歴](https://github.com/pieceofcake2/upgrade/commits/1.x)
で確認できます。
