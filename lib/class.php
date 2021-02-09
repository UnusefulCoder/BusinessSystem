<?php

include 'lib/Template.php';

final class Server
{
    private function __construct() { }

    static function has($key)
    {
        return isset($_SERVER[$key]);
    }
    
    static function get($key)
    {
        return (isset($_SERVER[$key]) ? $_SERVER[$key] : null);
    }

    static function set($key, $value)
    {
        $_SERVER[$key] = $value;
    }

    static function forget($key)
    {
        unset($_SERVER[$key]);
    }
}
final class Session
{
    private function __construct() { }

    static function has($key)
    {
        return isset($_SESSION[$key]);
    }
    
    static function get($key)
    {
        return (isset($_SESSION[$key]) ? $_SESSION[$key] : null);
    }

    static function set($key, $value)
    {
        $_SESSION[$key] = $value;
    }

    static function forget($key)
    {
        unset($_SESSION[$key]);
    }
}
final class Get
{
    private function __construct() { }

    static function has($key)
    {
        return isset($_GET[$key]);
    }

    static function hasAll($keys)
    {
        foreach ($keys as $key) {
            if (!isset($_GET[$key])) return false;
        }

        return true;
    }
    
    static function get($key)
    {
        return (isset($_GET[$key]) ? $_GET[$key] : null);
    }

    static function set($key, $value)
    {
        $_GET[$key] = $value;
    }

    static function forget($key)
    {
        unset($_GET[$key]);
    }
}
final class Post
{
    private function __construct() { }

    static function has($key)
    {
        return isset($_POST[$key]);
    }

    static function hasAll($keys)
    {
        foreach ($keys as $key) {
            if (!isset($_POST[$key])) return false;
        }

        return true;
    }
    
    static function get($key)
    {
        return (isset($_POST[$key]) ? $_POST[$key] : null);
    }

    static function set($key, $value)
    {
        $_POST[$key] = $value;
    }

    static function forget($key)
    {
        unset($_POST[$key]);
    }
}

abstract class Conexao
{
    public static mysqli $conexao;
    /**
     * @var mysqli_result|bool
     */
    public static $resultado;
    
    protected function __construct() { }

    public static function escape(string $value): string
    {
        if (!isset(self::$conexao)) self::conectar();

        return "'" . self::$conexao->escape_string($value) . "'";
    }

    protected static function query($query): int
    {
        if (!isset(self::$conexao)) self::conectar();

        self::$resultado = self::$conexao->query($query);

        return self::$conexao->affected_rows;
    }

    public static function validar($tipo, $valor, $min = false, $max = false)
    {
        if ($tipo == 'usuario') $pattern = '/[a-zA-ZáàãâäéèêëíìîïóòôöúùûüñýÿçÁÀÃÂÄÉÈÊËÍÌÎÏÓÒÔÖÚÙÛÜÑÇ]{1,30}/';
        if ($tipo == 'nome') $pattern = '/[a-zA-ZáàãâäéèêëíìîïóòôöúùûüñýÿçÁÀÃÂÄÉÈÊËÍÌÎÏÓÒÔÖÚÙÛÜÑÇ ]{1,100}/';
        if ($tipo == 'senha') $pattern = '/[a-zA-Z0-9!@#$%&*,.:;-_+=?]{8,}/';
        if ($tipo == 'inteiro') {
            if ($min && $max) {
                if ((int)$valor >= $min && (int)$valor <= $max) $pattern = '/[[:digit:]]/';
            } else $pattern = '/[[:digit:]]/';
        }

        if (!isset($pattern) || !preg_match($pattern, $valor)) throw new InvalidArgumentException('Dados inválidos!');

        return self::escape($valor);
    }

    private static function conectar(): void
    {
        self::$conexao = new mysqli("localhost", "sistema", "sistema123", "sistema");

        if (self::$conexao->connect_errno) throw new mysqli_sql_exception("Falha na conexão com o banco de dados!");
    }
}

function mostrarHome()
{
    $tpl = new \raelgc\view\Template('home.html');

    $tpl->NOME = Session::get('nome');
    $tpl->IP = Server::get('REMOTE_ADDR') == '::1' ? '127.0.0.1' : Server::get('REMOTE_ADDR');

    $tpl->ELEMENTO = "<script defer>document.getElementById('home').classList.add('active')</script>";

    $tpl->addFile('TOPO', 'topo.html');

    if (Session::has('status')) {
        $tpl->SCRIPT = '<script defer>setTimeout(() => showSnackbar("' . Session::get('status') . '"), 1);</script>';

        Session::forget('status');
    }

    $tpl->show();
}

class Usuarios extends Conexao
{
    private function __construct() { }

    static function mostrar()
    {
        if (Get::get('pg') < 1) $pagina = 1;
        else $pagina = Get::get('pg');

        $usuariosQuant = self::queryUsuario('COUNT');
        $maxPagina = (int)ceil($usuariosQuant / 10);

        if ($pagina > $maxPagina) $pagina = $maxPagina;

        $tpl = new \raelgc\view\Template('usuarios.html');
        $tpl->addFile('TOPO', 'topo.html');

        $tpl->TITULO = 'Gerenciar Usuários';
        $tpl->ELEMENTO = "<script>document.getElementById('gerenciar').classList.add('active'); document.getElementById('usuarios-mostrar').classList.add('active');</script>";

        $tpl->USUARIOS = $usuariosQuant;
        $tpl->MAXPAGINA = $maxPagina;
        $tpl->PAGINA = $pagina;

        if ($pagina == 1) {
            $tpl->DES_1 = 'disabled';
            if ($maxPagina == 1) $tpl->DES_2 = 'disabled';
        }
        if ($pagina == $maxPagina) $tpl->DES_3 = 'disabled';

        if (self::queryUsuario('SELECT', false, false, ($pagina - 1) * 10, 10)) {
            foreach (self::$resultado->fetch_all() as $usuario) {
                $tpl->ID = $usuario[0];
                $tpl->NOME = $usuario[2];
                $tpl->USUARIO = $usuario[1];
                $tpl->PRIVILEGIO = $usuario[3];
                $tpl->block('TABELA_LINHA');
            }
        }

        $tpl->block('TABELA');

        if (Session::has('status')) {
            $tpl->SCRIPT = '<script defer>setTimeout(() => showSnackbar("' . Session::get('status') . '"), 1);</script>';
    
            Session::forget('status');
        }

        $tpl->show();
    }

    static function incluir()
    {
        $tpl = new \raelgc\view\Template('usuarios.html');

        try {
            if (Post::hasAll(['criarUsuario', 'criarNome', 'criarPrivilegio', 'criarSenha'])) {
                if (self::queryUsuario('SELECT', 'usuario=' . self::escape(Post::get('criarUsuario')))) throw new InvalidArgumentException('Nome de usuário indisponível!');

                if (self::queryUsuario('INSERT', self::validar('usuario', Post::get('criarUsuario')) . ', ' . self::validar('nome', Post::get('criarNome')) . ', ' . self::validar('inteiro', Post::get('criarPrivilegio'), 0, 3) . ', PASSWORD(' . self::validar('senha', Post::get('criarSenha')) . ')')) {
                    Session::set('status', 'Sucesso!');
                    header('Location: ?pagina=usuarios');
                    return;
                }
            }
        } catch (InvalidArgumentException | mysqli_sql_exception $e) {
            $tpl->SCRIPT = '<script defer>setTimeout(() => showSnackbar("' . $e->getMessage() . '"), 1);</script>';

            $tpl->VALUE_USUARIO = 'value=' . Post::get('criarUsuario');
            $tpl->VALUE_NOME = 'value="' . Post::get('criarNome') . '"';
            $tpl->__set('PRIVILEGIO_' . Post::get('criarPrivilegio'), 'selected');
            $tpl->VALUE_SENHA = 'value="' . Post::get('criarSenha') . '"';
        }

        $tpl->addFile('TOPO', 'topo.html');

        $tpl->TITULO = 'Gerenciar Usuários - Novo Usuário';
        $tpl->ELEMENTO = "<script defer>document.getElementById('usuarios-incluir').classList.add('active');</script>";

        $tpl->block('INCLUIR');
        $tpl->show();
    }

    static function alterar()
    {
        if (!Get::has('id')) {
            Session::set('status', 'Escolha o usuário para editar!');
            header('Location: ?pagina=usuarios');
            return;
        }

        if (!self::queryUsuario('SELECT', 'id=' . self::escape(Get::get('id')))) {
            Session::set('status', 'Usuário não encontrado!');
            header('Location: ?pagina=usuarios');
            return;
        }

        $resultado = self::$resultado->fetch_assoc();

        $tpl = new \raelgc\view\Template('usuarios.html');

        $tpl->ID = $resultado['id'];
        $tpl->USUARIO = $resultado['usuario'];
        $tpl->NOME = $resultado['nome'];
        $tpl->__set('PRIVILEGIO_' . $resultado['privilegio'], 'selected');

        try {
            if (Post::hasAll(['editarID', 'editarUsuario', 'editarNome', 'editarPrivilegio', 'editarSenha'])) {
                if (!self::queryUsuario('SELECT', 'id=' . self::escape(Post::get('editarID')))) {
                    Session::set('status', 'Usuário não encontrado!');
                    header('Location: ?pagina=usuarios');
                    return;
                }

                $resultado = self::$resultado->fetch_assoc();

                $query = '';
                $query .= $resultado['nome'] != Post::get('editarNome') ? 'nome=' . self::validar('nome', Post::get('editarNome')) . ', ' : '';
                $query .= $resultado['privilegio'] != Post::get('editarPrivilegio') ? 'privilegio=' . self::validar('inteiro', Post::get('editarPrivilegio'), 0, 3) . ', ' : '';

                if (!strlen(Post::get('editarSenha')) == 0) $query .= 'senha=PASSWORD(' . self::validar('senha', Post::get('editarSenha')) . '), ';

                if (strlen($query) == 0) throw new InvalidArgumentException('Nada para editar!');

                if (self::queryUsuario('UPDATE', 'id=' . self::escape(Post::get('editarID')), substr($query, 0, -2))) {
                    Session::set('status', 'Sucesso!');
                    header('Location: ?pagina=usuarios');
                    return;
                }
            }
        } catch (InvalidArgumentException | mysqli_sql_exception $e) {
            $tpl->SCRIPT = '<script defer>setTimeout(() => showSnackbar("' . $e->getMessage() . '"), 1);</script>';

            $tpl->NOME = Post::get('editarNome');
            $tpl->__set('PRIVILEGIO_' . Post::get('editarPrivilegio'), 'selected');
            $tpl->VALUE_SENHA = 'value=' . Post::get('editarSenha');
        }

        $tpl->addFile('TOPO', 'topo.html');

        $tpl->TITULO = 'Gerenciar Usuários - Editar Usuário';
        $tpl->ELEMENTO = "<script defer>document.getElementById('usuarios-alterar').classList.add('active');</script>";

        $tpl->block('ALTERAR');
        $tpl->show();
    }

    static function remover()
    {
        if (Get::has('id')) {
            if (self::queryUsuario('SELECT', 'id=' . self::escape(Get::get('id'))) == 0) Session::set('status', 'Usuário não encontrado!');
            else Session::set('status', self::queryUsuario('DELETE', 'id=' . self::escape(Get::get('id'))) ? 'Sucesso!' : 'Falha!');
        } else {
            Session::set('status', 'Escolha o usuário para excluir!');
        }

        header('Location: ?pagina=usuarios');
    }

    static function pesquisar()
    {
        if (!Post::has('pesquisa') || Post::get('pesquisa') == '') {
            header('Location: ?pagina=usuarios');
            return;
        }

        if (is_int(Post::get('pesquisa'))) $resultado = self::queryUsuario('SELECT', 'id=' . self::escape(Post::get('pesquisa')));
        else $resultado = self::queryUsuario('SELECT', 'usuario LIKE "%' . Post::get('pesquisa') . '%" OR nome LIKE "%' . Post::get('pesquisa') . '%"');

        $tpl = new \raelgc\view\Template('usuarios.html');

        $tpl->PAGINA = $tpl->MAXPAGINA = 1;
        $tpl->DES_1 = $tpl->DES_2 = $tpl->DES_3 = 'disabled';

        $tpl->USUARIOS = $resultado;

        if ($resultado > 0) {
            foreach (self::$resultado->fetch_all() as $usuario) {
                $tpl->ID = $usuario[0];
                $tpl->NOME = $usuario[2];
                $tpl->USUARIO = $usuario[1];
                $tpl->PRIVILEGIO = $usuario[3];
                $tpl->block('TABELA_LINHA');
            }
        }

        $tpl->block('TABELA');

        $tpl->addFile('TOPO', 'topo.html');

        $tpl->show();
    }

    static function queryUsuario($acao, $condicao = false, $campos = false, $comeco = false, $quantidade = false, $ordenacao = false)
    {
        if ($acao == 'SELECT') return self::query('SELECT id, usuario, nome, privilegio FROM usuarios' . ($condicao ? ' WHERE ' . $condicao : '') . ($ordenacao ? ' ORDER BY ' . $ordenacao : '') . (is_int($comeco) && is_int($quantidade) ? ' LIMIT ' . $comeco . ',' . $quantidade : ''));
        if ($acao == 'INSERT' && $condicao) return self::query('INSERT INTO usuarios (usuario, nome, privilegio, senha) VALUES (' . $condicao . ')');
        if ($acao == 'UPDATE' && $condicao && $campos) return self::query('UPDATE usuarios SET ' . $campos . ' WHERE ' . $condicao);
        if ($acao == 'DELETE' && $condicao) return self::query('DELETE FROM usuarios WHERE ' . $condicao);
        if ($acao == 'COUNT') {
            self::query('SELECT COUNT(id) FROM usuarios');

            return self::$resultado->fetch_assoc()['COUNT(id)'];
        }

        return 0;
    }
}
