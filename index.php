<?php

require 'vendor/autoload.php';
include 'lib/class.php';

session_start([
    'cookie_secure' => 'true',
    'cookie_httponly' => 'true',
    'cookie_samesite' => 'Lax',
]);

if (!isset($_SESSION["id"])) {
    if (!isset($_GET['pagina']) || $_GET['pagina'] != 'login') {
        header('Location: ?pagina=login');
        exit;
    }

    try {
        if (Post::has('usuario') && Post::has('senha')) {
            if (Usuarios::queryUsuario('SELECT', 'usuario=' . Usuarios::escape(Post::get('usuario')) . ' AND senha=PASSWORD(' . Usuarios::escape(Post::get('senha')) . ')')) {
                $resultado = $usuarios->resultado->fetch_assoc();

                $_SESSION["id"] = $resultado["id"];
                $_SESSION["usuario"] = $resultado["usuario"];
                $_SESSION["nome"] = $resultado["nome"];
                $_SESSION["privilegio"] = $resultado["privilegio"];
            } else throw new InvalidArgumentException('Dados inválidos!');
        } else {
            $tpl = new \raelgc\view\Template('login.html');
            $tpl->show();
            exit;
        }
    } catch (InvalidArgumentException | mysqli_sql_exception $e) {
        $tpl = new \raelgc\view\Template('login.html');
        $tpl->SCRIPT = '<script>setTimeout(() => showSnackbar("' . $e->getMessage() . '"), 1);</script>';
        $tpl->show();
        exit;
    }
}

if (isset($_GET['pagina'])) switch ($_GET['pagina']) {
    case 'usuarios':
        Usuarios::mostrar();
        break;
    case 'usuarios_incluir':
        Usuarios::incluir();
        break;
    case 'usuarios_alterar':
        Usuarios::alterar();
        break;
    case 'usuarios_remover':
        Usuarios::remover();
        break;
    case 'usuarios_pesquisar':
        Usuarios::pesquisar();
        break;
    case 'logout':
        session_regenerate_id(true);
        session_destroy();
        header('Location: ?pagina=login');
        break;
    default:
        mostrarHome();
}
else mostrarHome();
