<?php
/**
 * Website Entry Point
 *
 * @file index.php
 */

require 'vendor/autoload.php';
require 'lib/class.php';

session_start(
    [
        'cookie_secure'   => 'true',
        'cookie_httponly' => 'true',
        'cookie_samesite' => 'Lax',
    ]
);

if (Session::get('id') !== null) {
    if (Get::get('pagina') !== 'login') {
        header('Location: ?pagina=login');
        return;
    }

    try {
        if (Post::has('usuario') === true && Post::has('senha') === true) {
            if (
                Usuarios::queryUsuario(
                    'SELECT',
                    'usuario='.Usuarios::escape(
                        Post::get('usuario')
                    ).' AND senha=PASSWORD('.Usuarios::escape(
                        Post::get('senha')
                    ).')'
                ) !== false
            ) {
                $resultado = $usuarios->resultado->fetch_assoc();

                Session::set('id', $resultado['id']);
                Session::set('usuario', $resultado['usuario']);
                Session::set('nome', $resultado['nome']);
                Session::set('privilegio', $resultado['privilegio']);
            } else {
                throw new InvalidArgumentException('Dados inválidos!');
            }
        } else {
            $tpl = new \raelgc\view\Template('login.html');
            $tpl->show();
            return;
        }//end if
    } catch (InvalidArgumentException | mysqli_sql_exception $e) {
        $tpl = new \raelgc\view\Template('login.html');

        $tpl->SCRIPT = '<script>setTimeout(() => showSnackbar("'.$e->getMessage().'"), 1);</script>';
        $tpl->show();
        return;
    }//end try
}//end if

switch (Get::get('pagina')) {
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
    break;
}//end switch
