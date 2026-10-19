# Upgrade ドキュメント

Upgrade プラグインは `upgrade` コンソールシェルを提供します。このシェルは、CakePHP 1.3 アプリケーションを
CakePHP 2 にアップグレードするために必要な機械的な変更の大部分を自動化します。
[pieceofcake2/cakephp](https://github.com/pieceofcake2/cakephp) で動作します。

- [インストール](#installation)
- [シェルの実行](#running-the-shell)
- [コマンド](#commands)
- [オプション](#options)
- [Rector のルール](#rector-rules)
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
| `rector` | pieceofcake2/cakephp の変更のための Rector のルールを適用します。`all` には含まれません。[Rector のルール](#rector-rules) を参照してください。 |

<a id="options"></a>

## オプション

| オプション | 説明 |
| --- | --- |
| `--plugin`、`-p` | 指定したプラグインだけを更新します。 |
| `--ext`、`-e` | 更新するファイルの拡張子。パイプ区切りのリストか、`preg_match()` 互換のサブパターンで指定します (デフォルト: `php\|ctp\|thtml\|inc\|tpl`)。 |
| `--git`、`-g` | Git が履歴を保持するように、`git mv` でファイルを移動します。カレントディレクトリは Git リポジトリである必要があります。 |
| `--dry-run`、`-d` | ファイルを変更せずに変更内容を表示します。 |

<a id="rector-rules"></a>

## Rector のルール

`rector` コマンドは、指定したディレクトリやファイルに [Rector](https://getrector.com/) のルールを適用します。
pieceofcake2/cakephp の変更に、アプリケーションのコードを合わせるためのものです。CakePHP 1.3 からのアップグレード
である `all` には含まれません。

```bash
# 変更を確認する
bin/cake upgrade rector --rules variadic-arguments src tests --dry-run

# 適用する
bin/cake upgrade rector --rules variadic-arguments src tests
```

| オプション | 説明 |
| --- | --- |
| `--rules`、`-r` | ルールのセット。プラグインの `config/rector/` のファイルです (デフォルト: `variadic-arguments`)。 |
| `--dry-run`、`-d` | ファイルを変更せずに、変更の内容を表示します。 |

### `variadic-arguments`

pieceofcake2/cakephp は、いくつかのメソッドの可変長の引数を、`func_get_args()` で読む代わりに可変長引数として
宣言します (例: `Model::query($sql, ...$params)`。一覧は pieceofcake2/cakephp の UPGRADE を参照してください)。
PHP は、これらを上書きするメソッドの引数が少ないとエラーにするため、このルールは、アプリケーションのクラスで
これらを上書きしているメソッドに、同じ可変長引数を追加します。

```php
// 変更前
class AppModel extends Model
{
    public function query($sql)
    {
        return parent::query(...func_get_args());
    }
}

// 変更後
class AppModel extends Model
{
    public function query($sql, ...$params)
    {
        return parent::query($sql, ...$params);
    }
}
```

- `func_get_args()` は、引数がすべて必須のときは引数の並びに置き換えます。省略できる引数がある場合はそのまま
  残します。`func_get_args()` は渡されなかった省略可能な引数を含まないため、並びに置き換えるとデフォルト値を
  渡すことになるからです。可変長引数を追加しても、`func_get_args()` は同じ値を返します。
- 可変長引数の名前は CakePHP と同じにします。メソッドにすでに同じ名前の引数や変数がある場合は、別の名前にします。
- 変更したコードは、この変更の前の pieceofcake2/cakephp でも動くので、更新する前にルールを適用できます。

### `namespaced-classes`

CakePHP 2.13 からは、pieceofcake2/cakephp のクラスは `Cake` 名前空間にあり (例: `Cake\Controller\Controller`)、
グローバルな名前は同じクラスの別名です。グローバルな名前はそのまま使えるので、このルールは任意です。コードの
グローバルな名前を `use` を付けた名前空間の名前に置き換え、`get_class()` で動かなくなるクラス名の比較を直します。

```php
// 変更前
class PostsController extends AppController
{
    public function beforeFilter()
    {
        if (get_class($this->request) === 'CakeRequest') {
            $email = new CakeEmail('default');
        }
    }
}

// 変更後
use Cake\Network\CakeRequest;
use Cake\Network\Email\CakeEmail;

class PostsController extends AppController
{
    public function beforeFilter()
    {
        if (get_class($this->request) === CakeRequest::class) {
            $email = new CakeEmail('default');
        }
    }
}
```

- コードの中の CakePHP のクラス (`extends`、`new`、型宣言、静的呼び出し、`instanceof`、`catch`) には `use` を
  付けます。docblock の中では完全修飾名になります (例: `@param \Cake\Controller\Controller`)。
- CakePHP のクラスのグローバルな名前の文字列は、オブジェクトのクラスと比べている場合に `::class` に置き換えます。
  対象は `get_class()`、`get_parent_class()`、`get_called_class()`、`$object::class`、`static::class` と、
  `===`、`==`、`!==`、`!=`、`in_array()`、`switch`、`match` での比較です。`get_class($x) === 'CakeRequest'`
  は前と同じクラスと比べます。`instanceof` はサブクラスにも一致するので使いません。
- ほかの文字列 (`App::uses('Controller', 'Controller')` や、メソッドに渡す `'CakeRequest'`) と、アプリケーションや
  プラグインのクラス (`AppController`、`AppModel`) はそのままです。
- 変更したコードには pieceofcake2/cakephp 2.13 以降が必要です。そのあとでコードスタイルの修正ツールを実行して、
  `use` を並べ替えてください。

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
