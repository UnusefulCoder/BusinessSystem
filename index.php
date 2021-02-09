<?php

include 'lib/class.php';

session_start([
    'cookie_secure' => 'true',
    'cookie_httponly' => 'true',
    'cookie_samesite' => 'Lax',
]);

if (!Session::has("id")) {
    if (Get::get('pagina') != 'login') {
        header('Location: ?pagina=login');
        return;
    }

    try {
        if (Post::has('usuario') && Post::has('senha')) {
            $usuarios = new Usuarios();

            if ($usuarios->queryUsuario('SELECT', 'usuario=' . $usuarios->escape(Post::get('usuario')) . ' AND senha=PASSWORD(' . $usuarios->escape(Post::get('senha')) . ')')) {
                $resultado = $usuarios->resultado->fetch_assoc();

                Session::set("id", $resultado["id"]);
                Session::set("usuario", $resultado["usuario"]);
                Session::set("nome", $resultado["nome"]);
                Session::set("privilegio", $resultado["privilegio"]);
            } else throw new InvalidArgumentException('Dados inválidos!');
        } else {
            $tpl = new \raelgc\view\Template('login.html');
            $tpl->show();
            return;
        }
    } catch (InvalidArgumentException | mysqli_sql_exception $e) {
        $tpl = new \raelgc\view\Template('login.html');
        $tpl->SCRIPT = '<script>setTimeout(() => showSnackbar("' . $e->getMessage() . '"), 1);</script>';
        $tpl->show();
        return;
    }
}

switch (Get::get('pagina')) {
    case 'usuarios':
        $usuarios = new Usuarios();
        $usuarios->mostrar();
        break;
    case 'usuarios_incluir':
        $usuarios = new Usuarios();
        $usuarios->incluir();
        break;
    case 'usuarios_alterar':
        $usuarios = new Usuarios();
        $usuarios->alterar();
        break;
    case 'usuarios_remover':
        $usuarios = new Usuarios();
        $usuarios->remover();
        break;
    case 'usuarios_pesquisar':
        $usuarios = new Usuarios();
        $usuarios->pesquisar();
        break;
    case 'logout':
        session_regenerate_id(true);
        session_destroy();
        header('Location: ?pagina=login');
        break;
    default:
        mostrarHome();
}
