<?php
// Arquivo temporário de diagnóstico. Suba, acesse pelo navegador, me mande o
// resultado e depois APAGUE este arquivo do servidor.
ini_set('display_errors', '1');
error_reporting(E_ALL);

$authOk = null;
$authErro = '';
try {
    require __DIR__ . '/auth.php';
    $authOk = true;
} catch (\Throwable $e) {
    $authOk = false;
    $authErro = $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine();
    if (class_exists('Logger')) {
        Logger::error('Falha ao carregar auth.php no diagnostico', ['exception' => $e]);
    }
}

header('Content-Type: text/plain; charset=UTF-8');

echo "PHP version: " . PHP_VERSION . "\n";
echo "pdo carregado: " . (extension_loaded('pdo') ? 'sim' : 'NAO') . "\n";
echo "pdo_mysql carregado: " . (extension_loaded('pdo_mysql') ? 'sim' : 'NAO') . "\n";
echo "config.php existe nesta pasta: " . (file_exists(__DIR__ . '/config.php') ? 'sim' : 'NAO') . "\n";
echo "auth.php existe nesta pasta: " . (file_exists(__DIR__ . '/auth.php') ? 'sim' : 'NAO') . "\n";

echo "\n--- Resultado do auth.php ---\n";
echo $authOk ? "auth.php carregado OK\n" : "ERRO no auth.php: {$authErro}\n";

echo "\n--- Testando conexao direta com o banco (le as variaveis do config.php) ---\n";
try {
    if (!file_exists(__DIR__ . '/config.php')) {
        throw new \RuntimeException('config.php nao existe nesta pasta do servidor.');
    }

    $conteudoConfig = file_get_contents(__DIR__ . '/config.php');
    preg_match('/\$DB_HOST\s*=\s*\'([^\']*)\'/', $conteudoConfig, $mHost);
    preg_match('/\$DB_NAME\s*=\s*\'([^\']*)\'/', $conteudoConfig, $mName);
    preg_match('/\$DB_USER\s*=\s*\'([^\']*)\'/', $conteudoConfig, $mUser);
    preg_match('/\$DB_PASS\s*=\s*\'([^\']*)\'/', $conteudoConfig, $mPass);

    echo "DB_HOST lido: " . ($mHost[1] ?? '(nao encontrado)') . "\n";
    echo "DB_NAME lido: " . ($mName[1] ?? '(nao encontrado)') . "\n";
    echo "DB_USER lido: " . ($mUser[1] ?? '(nao encontrado)') . "\n";
    echo "DB_PASS lido: " . (isset($mPass[1]) && $mPass[1] !== '' ? '(preenchida, ' . strlen($mPass[1]) . ' caracteres)' : '(VAZIA)') . "\n";

    // Conecta direto (sem passar pelo die() interno do config.php) pra mostrar o erro real do PDO.
    $pdoTeste = new PDO(
        "mysql:host={$mHost[1]};dbname={$mName[1]};charset=utf8mb4",
        $mUser[1],
        $mPass[1] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "Conexao com banco OK\n";

    $teste = $pdoTeste->query('SELECT COUNT(*) AS total FROM funcionarios')->fetch();
    echo "Funcionarios cadastrados: " . $teste['total'] . "\n";
} catch (\Throwable $e) {
    echo "ERRO na conexao/consulta: " . $e->getMessage() . "\n";
    echo "Em: " . $e->getFile() . ":" . $e->getLine() . "\n";
    if (class_exists('Logger')) {
        Logger::error('Falha na conexao/consulta de diagnostico', [
            'db_host' => $mHost[1] ?? null,
            'db_name' => $mName[1] ?? null,
            'exception' => $e,
        ]);
    }
}
