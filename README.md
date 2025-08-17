# Orderer Migrations

Pacote Laravel para rodar migrations customizadas em ordem definida.

## Funcionalidades

- Permite definir uma lista de migrations customizadas (nomeadas ou anônimas) para serem executadas em ordem específica.
- Suporta tanto migrations padrão do Laravel quanto arquivos de migration anônimos.
- Executa o método `up()` de cada migration, pulando automaticamente migrations já aplicadas (baseado na existência da tabela).
- Executa o método `down()` de cada migration, revertendo na ordem inversa e pulando migrations já revertidas.
- Registra e remove as migrations na tabela padrão `migrations` do Laravel, mantendo o histórico/batch.
- Detecta automaticamente o nome da tabela criada/removida pela migration (por propriedade, método ou parsing do método `up`).
- Comando Artisan `migrate:base` para rodar todas as migrations customizadas em ordem, com suporte a `up` e `down`.
- Não requer registro manual do Service Provider graças ao Laravel Package Discovery.

## Instalação

```shell
composer require shrrgnien/orderer-migrations
```

## Uso


# Sequencial Migrations
	 - Edite a propriedade `$migrations` em `src/BaseMigration.php` e adicione os nomes das suas migrations.
Pacote Laravel para rodar migrations customizadas de forma sequencial (em ordem definida).
	 - Você pode misturar migrations nomeadas (classes) e migrations anônimas (arquivos que retornam um objeto Migration).
	 - Exemplo:
		 ```php
		 protected array $migrations = [
				 // Migration nomeada (classe PHP)
				 'CreateProdutosTable',
				 // Migration anônima (arquivo migration)
				 '2025_08_11_135652_create_sis_solicitacoes_table',
		 ];
		 ```
	 - Para migrations nomeadas, use apenas o nome da classe (sem namespace).
	 - Para migrations anônimas, use o nome do arquivo (sem extensão .php).

2. **Coloque suas migrations customizadas na pasta padrão do Laravel**
	 - Os arquivos devem estar em `database/migrations` do seu projeto Laravel.
composer require shrrgnien/sequencial-migrations
3. **Execute as migrations em ordem**
	 - Use o comando Artisan:
		 ```shell
		 php artisan migrate:base
		 ```
	 - Para reverter (down):
	 - Edite a propriedade `$migrations` em `src/BaseMigration.php` e adicione os nomes das suas migrations.
	 - Você pode misturar migrations nomeadas (classes) e migrations anônimas (arquivos que retornam um objeto Migration).
	 - Exemplo:
		 ```php
		 protected array $migrations = [
				 // Migration nomeada (classe PHP)
				 'CreateProdutosTable',
				 // Migration anônima (arquivo migration)
				 '2025_08_11_135652_create_sis_solicitacoes_table',
		 ];
		 ```
	 - Para migrations nomeadas, use apenas o nome da classe (sem namespace).
	 - Para migrations anônimas, use o nome do arquivo (sem extensão .php).
## Exemplo de migration nomeada

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProdutosTable extends Migration
{
	public function up()
	{
		Schema::create('produtos', function (Blueprint $table) {
			$table->id();
			$table->string('nome');
			$table->timestamps();
		});
	}

	public function down()
	{
		Schema::dropIfExists('produtos');
	}
}
```

Depois, adicione 'CreateProdutosTable' no array `$migrations` da sua `BaseMigration`.

## Licença

MIT
