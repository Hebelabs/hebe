<?php

$conn = new mysqli(
        $_ENV['MYSQL_HOST'],
        $_ENV['MYSQL_USER'],
        $_ENV['MYSQL_PASSWORD'],
        $_ENV['MYSQL_DATABASE'],
        $_ENV['MYSQL_PORT']);

if ($conn->connect_error) {
    die("Ошибка соединения: " . $conn->connect_error);
}
echo "Соединение с базой установлено";
echo "<br>";
echo "Версия сервера " . $_ENV['SERVER_SOFTWARE'];


try {
    $db = new PDO(
        "mysql:dbname=" . $_ENV['MYSQL_DATABASE'] . ";port= " . $_ENV['MYSQL_PORT'] . ";host=" . $_ENV['MYSQL_HOST'],
        $_ENV['MYSQL_USER'],
        $_ENV['MYSQL_PASSWORD']);

        var_dump($db);

} catch (PDOException $e) {
	die($e->getMessage());
}

