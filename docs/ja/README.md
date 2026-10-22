# Upgrade ドキュメント

Upgrade プラグインは `upgrade` コンソールシェルを提供します。このシェルは、CakePHP 1.3 アプリケーションを
CakePHP 2 にアップグレードするために必要な機械的な変更の大部分を自動化します。
[pieceofcake2/cakephp](https://github.com/pieceofcake2/cakephp) で動作します。また、アプリケーションやプラグインを
[pieceofcake2/app](https://github.com/pieceofcake2/app) の CakePHP 5 形式の構成と名前空間付きのクラスに移し、
pieceofcake2/cakephp の変更に合わせたコードの書き換えを [Rector](https://getrector.com/) で行います。

- [インストール](#installation)
- [シェルの実行](#running-the-shell)
- [コマンド](#commands)
- [オプション](#options)
- [Rector のルール](#rector-rules)
- [構成と名前空間](#layout-and-namespace)
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
| `layout` | アプリケーションを CakePHP 2 の構成 (`app/Config/`、`app/Controller/` など) から pieceofcake2/app の構成 (`config/`、`src/`、`templates/` など) に移します。プラグインでは設定・テンプレート・翻訳・テストを移します。`all` には含まれません。[構成と名前空間](#layout-and-namespace) を参照してください。 |
| `namespace` | アプリケーションやプラグインのクラスを名前空間 (`App\`、またはプラグイン名) に移し、`App.namespace` と PSR-4 を設定します。`all` には含まれません。[構成と名前空間](#layout-and-namespace) を参照してください。 |

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
| `--no-short-names` | `class-name-strings` で短い名前を変えずに残します。[`class-name-strings`](#class-name-strings) を参照してください。 |

ルールが確認を必要とする箇所を残した場合は、変更の後に `Review these places:` の下に一覧が表示されます。

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
        if (get_class($this->response) === 'CakeResponse') {
            $this->set('plain', true);
        }
    }
}

// 変更後
use Cake\Network\CakeRequest;
use Cake\Network\CakeResponse;
use Cake\Network\Email\CakeEmail;

class PostsController extends AppController
{
    public function beforeFilter()
    {
        if ($this->request instanceof CakeRequest) {
            $email = new CakeEmail('default');
        }
        // CakeResponse には CakePHP の中にサブクラスがある
        if (get_class($this->response) === CakeResponse::class) {
            $this->set('plain', true);
        }
    }
}
```

- コードの中 (`extends`、`new`、型宣言、静的呼び出し、`instanceof`、`catch`) と docblock の中の CakePHP のクラスには
  `use` を付けます (例: `use Cake\Controller\Controller;` を付けて `@param Controller`)。短い名前がファイルの中で
  すでに別のクラスを指している場合は、完全修飾名のままにします。インストールされている pieceofcake2/cakephp の
  バージョンにかかわらず、同じ結果になります。
- オブジェクトのクラスと比べている、CakePHP のクラスのグローバルな名前の文字列を書き換えます。対象は
  `get_class()`、`get_parent_class()`、`get_called_class()`、`$object::class`、`static::class` と、
  `===`、`==`、`!==`、`!=`、`in_array()`、`switch`、`match` での比較です。
  - `get_class($x) === 'CakeRequest'` (`!==`、`==`、`!=`、`$x::class`、`in_array(get_class($x), [...])` も) は、
    CakePHP にも処理するコードにも `CakeRequest` を継承するクラスがなければ `$x instanceof CakeRequest` に
    なります。この場合 `instanceof` は同じオブジェクトにだけ一致します。ほかのパッケージ (`vendor/`) のクラスは
    調べないので、そこに継承するクラスがある場合は比較に戻してください。
  - 継承するクラスがある場合 (`Model`、`Component`、`CakeResponse` など) は `::class` との比較
    (`get_class($x) === CakeResponse::class`) のままにし、前と同じクラスと比べます。こうして残した箇所は、確認用に
    一覧で表示します。`get_parent_class()`、`get_called_class()`、`static::class`、`switch`、`match` はいつも
    `::class` との比較にします。
- ほかの文字列 (`App::uses('Controller', 'Controller')` や、メソッドに渡す `'CakeRequest'`) と、アプリケーションや
  プラグインのクラス (`AppController`、`AppModel`) はそのままです。
- 変更したコードには pieceofcake2/cakephp 2.13 以降が必要です。そのあとでコードスタイルの修正ツールを実行して、
  `use` を並べ替えてください。

<a id="class-name-strings"></a>

### `class-name-strings`

CakePHP 2 の設定は、クラスを文字列の名前で指定します (`public $components = ['Auth']`)。このルールは、その
クラス名を `use` を付けた `::class` に置き換え、コードが使うクラスをコードの上で分かるようにします。

```php
// 変更前
public $components = ['Session', 'Auth' => ['authenticate' => ['Form']]];
public $uses = ['Post'];

Configure::write('Exception.renderer', 'AppExceptionRenderer');

// 変更後
use App\Error\AppExceptionRenderer;
use App\Model\Post;
use Cake\Controller\Component\Auth\FormAuthenticate;
use Cake\Controller\Component\AuthComponent;
use Cake\Controller\Component\SessionComponent;

public $components = [SessionComponent::class, AuthComponent::class => ['authenticate' => [FormAuthenticate::class]]];
public $uses = [Post::class];

Configure::write('Exception.renderer', AppExceptionRenderer::class);
```

```bash
bin/cake upgrade rector --rules class-name-strings src tests config
```

- 書き換えるのは、クラス名を受け取る場所だけです: `$components`、`$helpers`、`$actsAs`、`$uses`、`$tasks`、
  モデルのアソシエーション (キー、`className`、`with`。`bindModel()` も)、`$viewClass`、Auth のオブジェクト
  (`authenticate`、`authorize`、`passwordHasher`)、`Cache::config()`・`CakeLog::config()`・`Session` のハンドラ・
  Text/Number/Time ヘルパーの `engine`、`DATABASE_CONFIG` と `ConnectionManager::create()` の `datasource`、
  `EmailConfig` と `CakeEmail::transport()` の `transport`、`Exception.renderer`、`Error.handler` と
  `Exception.handler` (`'ErrorHandler::handleError'` は `[ErrorHandler::class, 'handleError']` になります。
  `consoleHandler` も同じです)、`Dispatcher.filters`、`Acl.classname`、`routeClass` と
  `Router::defaultRouteClass()`、RequestHandlerComponent の `viewClassMap` (設定と `viewClassMap()`)、
  `Components->load()`、`Helpers->load()`、`Behaviors->load()` (`attach()` も)、`loadModel()`、
  `ClassRegistry::init()`。ほかの文字列 (`$this->Paginator->paginate('Post')` など) はそのままです。
- 名前は、CakePHP がその名前で見つけるクラスになります。`App.namespace` にあるアプリケーションのクラス、
  `'Plugin.Name'` ならそのプラグインのクラス、CakePHP のクラスの順に探します。CakePHP はバックスラッシュの
  ないクラス名を短い名前として扱うので、使うのは名前空間付きのクラスだけです。グローバルなクラスの名前と、
  処理するコードにも CakePHP にもない名前はそのままにします。`rector` コマンドは、アプリケーションの
  `App.namespace` をルールに渡します。
- `className` を持つキーは別名なので、そのままにします (`'Mailer' => ['className' => 'Greeting']` は
  `'Mailer' => ['className' => GreetingComponent::class]` になります)。キーにしたクラス名は、これまでどおり
  短い名前が別名になります (`AuthComponent::class` は `$this->Auth`)。
- 完全なクラス名 (`'AppExceptionRenderer'`、`'DbAcl'`、モデルの `'Post'`) はいつも書き換えます。CakePHP が
  サフィックスやプラグインを補う短い名前 (`AuthComponent` の `'Auth'`、`'DebugKit.Toolbar'`、
  `'Database/Mysql'`) も書き換えますが、`--no-short-names` を付けると (または `config/rector/class-name-strings.php`
  をコピーして `$shortNames` を `false` にすると) 残します。短い名前はアプリケーションの実行時に探すので、
  アプリケーションやプラグインが同じ名前のクラスで CakePHP のクラスを置き換えられます。`::class` にすると、
  使うクラスが一つに決まります。
- 変更したコードには pieceofcake2/cakephp 2.13 以降が必要です。

<a id="layout-and-namespace"></a>

## 構成と名前空間

`layout` と `namespace` は、pieceofcake2/cakephp で動くアプリケーションやプラグインを
[pieceofcake2/app](https://github.com/pieceofcake2/app) の CakePHP 5 形式の構成に移し、そのあとでクラスを
名前空間に移します。`all` には含まれません。この順に実行し、それぞれの結果を確認してからコミットしてください。

```bash
# 1. CakePHP 2 の構成 (app/Config/、app/Controller/、app/View/ など) を config/、src/、templates/ などに移す
app/Console/cake upgrade layout --dry-run
app/Console/cake upgrade layout
composer dump-autoload

# 2. クラスを App 名前空間に移す
bin/cake upgrade namespace --dry-run
bin/cake upgrade namespace
composer dump-autoload
```

プロジェクトのルート (`app/` か `config/` があるディレクトリ) で実行してください。Git リポジトリでは、Git が
履歴を保てるように `git mv` でファイルを移します。Git リポジトリでなければ `rename()` で移し、その旨を表示します。
ファイルを上書きすることはありません。移動先にファイルがあれば移動を飛ばし、一覧で表示します。`--dry-run` を
付けると、何も変更せずに移動の一覧を表示し、`namespace` では変更の内容を diff で表示します。

| オプション | 説明 |
| --- | --- |
| `--plugin`、`-p` | アプリケーションの代わりに、指定したプラグインを変更します。 |
| `--namespace`、`-n` | アプリケーションの `namespace` で使う名前空間です (デフォルト: `App`。例: `--namespace Acme`)。プラグインの名前空間はいつもプラグイン名です。 |
| `--no-short-names` | `namespace` で、設定の短い名前を残します。[`class-name-strings`](#class-name-strings) を参照してください。 |
| `--dry-run`、`-d` | 何も変更せずに、移動と変更の内容を表示します。 |

<a id="layout"></a>

### `layout`

アプリケーションは、CakePHP 2 の構成から pieceofcake2/app の構成に次のように移ります。

| 移動元 | 移動先 |
| --- | --- |
| `app/Config/` | `config/` |
| `app/Controller/`、`app/Model/`、`app/Lib/`、`app/Console/Command/`、`app/Console/Templates/` | `src/Controller/`、`src/Model/`、`src/Lib/`、`src/Console/Command/`、`src/Console/Templates/` |
| `app/View/Helper/` と `app/View/` のビュークラス (`AppView.php`) | `src/View/Helper/`、`src/View/` |
| `app/View/` のテンプレート | `templates/` |
| `app/Locale/` | `resources/locales/` |
| `app/Test/Case/`、`app/Test/Fixture/` | `tests/TestCase/`、`tests/Fixture/` |
| `app/Plugin/<Name>/` | `plugins/<Name>/` |
| `app/Vendor/` | `vendors/` (`App::import('Vendor', ...)` が手でコピーしたライブラリを探す場所で、Composer は管理しません) |
| `app/webroot/` (`index.php` と `test.php` を除く)、`app/tmp/logs/`、`app/tmp/` | `webroot/`、`logs/`、`tmp/` |

CakePHP 2 の構成のエントリーファイルはその構成のパスを定義しているので、pieceofcake2/app のエントリーファイルに
置き換えます。`config/define.php`、`bin/cake`、`webroot/index.php`、`webroot/test.php`、`webroot/.htaccess` と
`tmp/`・`logs/` のディレクトリは、なければ作成します (すでに新しい構成のアプリケーションでも同じです)。これらの
ファイルは pieceofcake2/app のファイルのコピーとしてこのプラグインの `resources/skeleton/` にあるので、
pieceofcake2/app のバージョンには左右されません。既存のファイルを上書きすることはありません。これらのファイルは
CakePHP のクラスをグローバルな名前 (`ShellDispatcher`、`Dispatcher`) で使うので、名前空間のないアプリケーションでも
動きます。`namespace` を実行すると `use` を付けます。

- 古いエントリーファイル (`app/webroot/index.php`、`app/webroot/test.php`、`app/Console/cake.php`) で定義している
  アプリケーション独自の定数 (例: `define('SITE_NAME', 'Blog')`) は、すべてのエントリーファイルが読み込む新しい
  `config/define.php` に追加します。CakePHP 2 の定数 (`ROOT`、`APP_DIR`、`WEBROOT_DIR`、`WWW_ROOT`、
  `CAKE_CORE_INCLUDE_PATH` など) は追加しません。新しいファイルが新しい構成に合わせて定義し、コアは Composer が
  読み込むからです。古いファイルのパス (`__FILE__`、`__DIR__`) から作る定数は、`ROOT` から定義し直せるよう一覧で
  表示します。
- Git リポジトリでは、古いエントリーファイル (`app/webroot/index.php`、`app/webroot/test.php`、`app/Console/cake`、
  `cake.php`、`cake.bat`) を `git rm` で削除します。Git の履歴には残ります。コミットしていない変更があるファイルは
  Git が削除を拒むので、そのまま残ります。Git リポジトリでなければ、比べてから削除できるように残します。

プラグインの `src/` には名前空間付きのクラスだけを置くので、プラグインのクラスはこの段階では移しません
(`namespace` で `src/` に移ります)。`Config/` は `config/` に、`View/` のテンプレートは `templates/` に、
`Locale/` は `resources/locales/` に、`Test/` は `tests/` に移ります。

テンプレートは、pieceofcake2/cakephp 2.13 が `templates/` で読む名前になります。テーマ (`Themed/<Theme>/`) や
プラグイン (`Plugin/<Plugin>/`) のディレクトリ、すでにある `templates/` の中も同じです。

- `.ctp` と `.thtml` は `.php` になります。ScaffoldView は `.ctp` しか読まないので、scaffold のテンプレート
  (`Scaffolds/`) は `.ctp` のままです。
- `Layouts/`、`Elements/`、`Emails/`、`Errors/` は `layout/`、`element/`、`email/`、`Error/` になります。
  メールのレイアウトは `Layouts/Emails/` から `layout/email/` に移ります。

`composer.json` のうち、移動したディレクトリの中を指すパスも移動先に合わせます。`autoload` と `autoload-dev` の
`classmap`、`files`、`psr-0`、`psr-4` のパス (`app/Controller/` は `src/Controller/` に) と、
`extra.installer-paths` (`app/Plugin/{$name}/` は `plugins/{$name}/` に) です。`app/` や `app/View/` のように
中のファイルがばらばらの場所に移ったパスは、書き換えずに一覧で表示します。

コマンドが一覧で示す、手で行う作業は次のとおりです。

- 古いエントリーファイルに定数以外の独自のコードがあれば、新しいファイルと比べて移してください。
- Web サーバーのドキュメントルートを `webroot/` にしてください。プロジェクトのルートにある、`app/webroot/` に
  つなぐ `index.php` や `.htaccess` は一覧で表示します。
- 移動しないファイル: `app/index.php`、`app/.htaccess` など、`app/` に残るファイルです。`vendors/` の
  ライブラリは、パッケージがあれば Composer でインストールしてください。
- `.thtml` のテンプレートを `.php` にした場合は、コントローラーやビューの `$ext = '.thtml'` を削除します。
- コードの中のパス (`APP . 'Config' . DS`、`App::build()`)、Web サーバーの設定、`.gitignore` (`app/tmp/`)、
  デプロイの設定にあるパスは変更しません。

<a id="namespace"></a>

### `namespace`

`namespace` は pieceofcake2/app の構成を前提とします。CakePHP 2 の構成 (`app/Config/`、プラグインでは
`Config/`、`Locale/`、`Test/`、`View/` のテンプレート) が見つかると、何も変更せずに止まり、先に `layout` を
実行するよう表示します。変更したコードには pieceofcake2/cakephp 2.13 以降が必要です。

まず、ファイルを移します。

- プラグインのクラスのフォルダ (`Controller/`、`Model/`、`View/`、`Console/`、`Lib/`) を `src/` に移します。
- CakePHP の種類 (`Cache`、`Configure`、`Console`、`Controller`、`Core`、`Error`、`Event`、`I18n`、`Log`、
  `Model`、`Network`、`Routing`、`TestSuite`、`Utility`、`View`) の `src/Lib/<Type>/` を `src/<Type>/` に移します。
  名前空間付きのコードには `Lib` がないので、`src/Lib/Error/AppExceptionRenderer.php` は `src/Error/` の
  `App\Error\AppExceptionRenderer` になります。`src/Lib/` の直下のファイルはそのまま残ります
  (例: `App\Lib\MyTool`)。
- アプリケーションにある CakePHP のクラスのコピー (コアクラスの上書き。例: `src/Lib/Routing/Dispatcher.php`) は
  `src/Cake/<path>` (`src/Cake/Routing/Dispatcher.php`) に移し、CakePHP の名前空間 (`Cake\Routing`) のまま、
  CakePHP のファイルと同じように `class_alias()` を付けます。古いバージョンのコピーかもしれないので、今の
  CakePHP のファイルと比べてください。

次に、Rector でコードを書き換えます。

- クラスの名前空間は、ファイルのパスから決まります。`src/Controller/` は `App\Controller`、
  `tests/TestCase/Controller/` は `App\Test\TestCase\Controller`、`tests/Fixture/` は `App\Test\Fixture`、
  プラグインでは `<Plugin>\...` です (CakePHP はプラグインのクラスを `<Plugin>\<Type>\<Class>` で探します)。
- Composer がクラスを読み込むので、`App::uses()` を削除します。
- アプリケーションと CakePHP のクラスを使う箇所は、コードでも docblock でも、`use` を付けた名前空間付きの名前に
  します。コードや docblock が使うグローバルなクラス (PHP、ほかのパッケージ、プラグインの中の `AppController`) にも
  `use` を付けます (`use Exception;`、`@param ArrayObject` には `use ArrayObject;`)。短い名前が別のクラス (ファイルの
  名前空間にあるクラスなど) を指す場合は、完全修飾名のままにします。
- PHP 自身がクラス名として読む文字列を `::class` にします。`class_exists()`、`is_a()`、`is_subclass_of()`、
  `method_exists()`、`new ReflectionClass()`、`call_user_func()` や `array_map()` などに渡す
  `['Class', 'method']` と `'Class::method'`、PHPUnit のモックとアサーション (`getMock()`、`getMockBuilder()`、
  `assertInstanceOf()`、`expectException()` など) です。
- オブジェクトのクラスとの比較を、[`namespaced-classes`](#namespaced-classes) と同じように、アプリケーションの
  クラスについても書き換えます。
- 設定のクラス名を [`class-name-strings`](#class-name-strings) と同じように書き換えます。`--no-short-names` を
  付けた場合に残る短い名前は、`App::className()` が見つけるのでそのまま動きます。
- `config/`、エントリーファイル (`bin/cake`、`webroot/` の PHP ファイル)、テンプレートは名前空間を付けずに、
  使っている CakePHP とアプリケーションのクラスに `use` を付けます (`Configure::write()` には
  `use Cake\Core\Configure;`)。変わるのは名前と設定のクラス名だけで、ほかのコードはそのままです。`use` は
  ファイルの docblock の後に置き、ファイルにもとからある `use` (`config/core.php` の
  `use Symfony\Component\Dotenv\Dotenv;`) はその先頭に残します。
- テンプレートでは、CakePHP 5 のテンプレートと同じように、先頭に `use` を置きます。テンプレートが PHP の
  ブロックで始まる場合はそのブロックの中 (docblock の後) に、そうでなければ新しいブロックに置きます。

  ```php
  <?php
  use Cake\Core\Configure;
  ?>
  <h2><?php echo h($message); ?></h2>
  <?php if (Configure::read('debug') > 0) : ?>
      <?php echo $this->element('exception_stack_trace'); ?>
  <?php endif; ?>
  ```

  クラスを使っていないテンプレートは変更しません。テンプレートの docblock の名前 (`@var View $this`) もそのままです。

そして、設定を変更します。

- `config/core.php` の `App.encoding` の docblock の直前に、短い docblock を付けて
  `Configure::write('App.namespace', 'App');` を追加します。CakePHP 5 でも名前空間はエンコーディングの前に
  設定します。`config/core.php` を読み込むときに実行される `Cache::config('_cake_core_')` が `App::className()`
  でエンジンを探すので、それより前に設定する必要があります。`App.encoding` の行がなければ最初の
  `Cache::config()` の前に追加し、どちらもなければ追加を求めるメッセージを表示します。`App.namespace` がすでに
  設定されていれば何も追加せず、値が違う場合はそれを表示します。`config/core.php` のほかの部分は変更しません。
- `composer.json` では、これらのディレクトリの classmap の代わりに、PSR-4 で `App\` を `src/` から、
  `App\Test\` を `tests/` から読み込むようにします (`autoload` と `autoload-dev`)。コアクラスの上書きがある
  場合は、classmap に `src/Cake/` を、`exclude-from-classmap` に CakePHP のファイルを追加します。ほかの項目と
  ファイルの書式はそのままです。プラグインは、プラグインの `composer.json` に (なければアプリケーションの
  `composer.json` に) PSR-4 を追加します。

アプリケーションより先にプラグインを名前空間に移してください。そうすると、アプリケーションの設定にある
クラス名 (`'Blog.Comment'`) を、名前空間に移したプラグインのクラスに書き換えられます。

`namespace` が変更しないもの (コマンドが一覧で表示するもの、ご自身で確認していただくもの) は次のとおりです。

- 関数も宣言しているファイル (名前空間を付けると関数の名前が変わります)、PHP タグの外に HTML があるファイルや
  `declare` ブロックのあるファイル、PHP として正しくないファイルは、グローバルのままです。複数のファイルで
  宣言されているクラスも、ほかのファイルでの使用箇所を書き換えません。
- アプリケーションやプラグインの外で、そのクラスをグローバルな名前で使うコード (プラグインをアプリケーションの
  後で名前空間に移した場合の、アプリケーションのコードなど) と、上に挙げた以外の文字列 (`paginate()` の
  `'Post'` など。CakePHP はモデルの別名として読みます)。
- 古いクラス名を含むデータ (キャッシュやセッションにシリアライズしたオブジェクト)。キャッシュを消してください。
- `use` の並び順。コードスタイルの修正ツールを実行してください。

実行した後は `composer dump-autoload` とテストを実行し、`git diff` で変更を確認してください。

<a id="after-the-upgrade"></a>

## アップグレードの後で

このシェルは、`app/Config/`、`app/Controller/`、`app/View/` などを使う CakePHP 2 のディレクトリ構成を作成します。
変更内容を確認し、テストを実行して、シェルが自動的に変換できなかった部分を修正してください。

pieceofcake2/cakephp は、CakePHP 5 スタイルの構成 (`config/`、`src/`、`templates/`) もサポートしています。
アップグレードしたアプリケーションをこの構成に移すには `layout` を、名前空間付きのクラスにするには続けて
`namespace` を実行してください。[構成と名前空間](#layout-and-namespace) を参照してください。

<a id="origin"></a>

## 由来

このパッケージは [cakephp/cakephp](https://github.com/cakephp/cakephp) (Packagist では
`cakephp/cakephp`、アップストリームの最終バージョンは 2.10.24) の `upgrade` コンソールシェルをもとに別のパッケージとして切り出したもので、
元の作者は CakePHP コミュニティ (Cake Software Foundation, Inc.) です。
[pieceofcake2](https://github.com/pieceofcake2) が、モダンな PHP で動く CakePHP 2 向けに
メンテナンスしています。オリジナルからの変更点は
[コミット履歴](https://github.com/pieceofcake2/upgrade/commits/1.x)
で確認できます。
