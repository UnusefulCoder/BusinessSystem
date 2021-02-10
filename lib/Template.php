<?php
/**
 * Template Management for PHP5
 *
 * @package    raelgc
 * @subpackage view
 * @author Rael G.C. (rael.gc@gmail.com)
 * @version 2.2.7
 */

namespace raelgc\view {

    /**
     * Template Management for PHP5
     *
     * The Template engine allows to keep the HTML code in some external files
     * which are completely free of PHP code. This way, it's possible keep
     * logical programmin (PHP code) away from visual structure (HTML or XML,
     * CSS, etc).
     *
     * If you are familiar with PHP template concept, this class includes these
     * features: object support, auto-detect blocks, auto-clean children blocks,
     * warning when user call for a non-existent block, warning when a
     * mal-formed block is detected, warning when user sets a non existant
     * variable, and other minor features.
     */
    class Template
    {

        /**
         * A list of existent document variables.
         *
         * @var array
         */
        protected $vars = [];

        /**
         * A hash with vars and values setted by the user.
         *
         * @var array
         */
        protected $values = [];

        /**
         * A hash of existent object properties variables in the document.
         *
         * @var array
         */
        private $properties = [];

        /**
         * A hash of the object instances setted by the user.
         *
         * @var array
         */
        protected $instances = [];

        /**
         * List of used modifiers.
         *
         * @var array
         */
        protected $modifiers = [];

        /**
         * A list of all automatic recognized blocks.
         *
         * @var array
         */
        private $blocks = [];

        /**
         * A list of all blocks that contains at least a "child" block.
         *
         * @var array
         */
        private $parents = [];

        /**
         * List of parsed blocks.
         *
         * @var array
         */
        private $parsed = [];

        /**
         * List of blocks to finalize.
         *
         * @var array
         */
        private $finally = [];

        /**
         * Describes the replace method for blocks. See the Template::setFile()
         * method for more details.
         *
         * @var boolean
         */
        private $accurate;

        /**
         * Regular expression to find var and block names.
         * Only alfa-numeric chars and the underscore char are allowed.
         *
         * @var string
         */
        private static $REG_NAME = '([[:alnum:]]|_)+';

        /**
         * Creates a new template, using $filename as main file.
         *
         * When the parameter $accurate is true, blocks will be replaced perfectly
         * (in the parse time), e.g., removing all \t (tab) characters, making the
         * final document an accurate version. This will impact (a lot) the
         * performance. Usefull for files using the &lt;pre&gt; or &lt;code&gt; tags.
         *
         * @param string  $filename File path of the file to be loaded.
         * @param boolean $accurate True for accurate block parsing.
         */
        public function __construct($filename, $accurate=false)
        {
            $this->accurate = $accurate;
            $this->loadfile('.', $filename);
        }

        /**
         * Put the content of $filename in the template variable identified by $varname.
         *
         * @param string $varname  Existing template var.
         * @param string $filename File to be loaded.
         *
         * @return void
         *
         * @throws \UnexpectedValueException When file does not exist.
         */
        public function addFile($varname, $filename)
        {
            if (!$this->exists($varname)) throw new \InvalidArgumentException("addFile: var $varname does not exist");

            $this->loadfile($varname, $filename);
        }

        /**
         * Do not use. Properties setter method.
         *
         * @param string $varname Template var name.
         * @param mixed  $value   Template var value.
         *
         * @return mixed Template var value.
         *
         * @throws \InvalidArgumentException When template var name does not exist.
         */
        public function __set($varname, $value)
        {
            if (!$this->exists($varname)) throw new \RuntimeException("var $varname does not exist");

            $stringValue = $value;

            if (is_object($value)) {
                $this->instances[$varname] = $value;

                if (!isset($this->properties[$varname])) $this->properties[$varname] = [];

                if (method_exists($value, '__toString')) $stringValue = $value->__toString();
                else $stringValue = 'Object';
            }

            $this->setValue($varname, $stringValue);

            return $value;
        }

        /**
         * Do not use. Properties getter method.
         *
         * @param string $varname Template var name.
         *
         * @return mixed Template var value.
         *
         * @throws \RuntimeException When template variable does not exist.
         */
        public function __get($varname)
        {
            return (isset($this->values['{'.$varname.'}']))
                ? $this->values['{'.$varname.'}']
                : ((isset($this->instances[$varname]))
                    ? $this->instances[$varname]
                    : throw new \RuntimeException("var $varname does not exist"));
        }

        /**
         * Check if a template var exists.
         *
         * This method returns true if the template var exists. Otherwise, false.
         *
         * @param string $varname Template var name.
         *
         * @return boolean True if the template var exists, false otherwise
         */
        public function exists($varname)
        {
            return in_array($varname, $this->vars);
        }

        /**
         * Loads a file identified by $filename.
         *
         * The file will be loaded and the file's contents will be assigned as the
         * variable's value.
         * Additionally, this method call Template::identify() that identifies
         * all blocks and variables automatically.
         *
         * @param string $varname  Contains the name of a variable to load.
         * @param string $filename File name to be loaded.
         *
         * @return void
         *
         * @throws \UnexpectedValueException When file is empty.
         */
        protected function loadfile($varname, $filename)
        {
            // if (!file_exists($filename)) throw new \InvalidArgumentException("file $filename does not exist");
            // If it's PHP file, parse it
            if ($this->isPHP($filename)) {
                ob_start();

                include $filename;
                $str = ob_get_contents();
                ob_end_clean();
                $this->setValue($varname, $str);

                return;
            }
            // Reading file and hiding comments
            $str = preg_replace('/<!---.*?--->/smi', '', file_get_contents($filename));

            if (empty($str)) throw new \InvalidArgumentException("file $filename is empty");

            $this->setValue($varname, $str);
            $blocks = $this->identify($str, $varname);
            $this->createBlocks($blocks);
        }

        /**
         * Check if file is a .php
         *
         * @param string $filename Name of the file.
         *
         * @return boolean True if $filename ends with .php, .php5 or .cgi. False otherwise.
         */
        protected function isPHP($filename)
        {
            foreach (['.php', '.php5', '.cgi'] as $php)
                if (0 == strcasecmp($php, substr($filename, strripos($filename, $php)))) return true;

            return false;
        }

        /**
         * Identify all blocks and variables automatically and return them.
         *
         * All variables and blocks are already identified at the moment when
         * user calls Template::setFile(). This method calls Template::identifyVars()
         * and Template::identifyBlocks() methods to do the job.
         *
         * @param string $content File content.
         * @param string $varname Contains the variable name of the file.
         *
         * @return array An array where the key is the block name and the value is an
         *               array with the children block names.
         */
        protected function identify(&$content, $varname)
        {
            $blocks       = [];
            $queuedBlocks = [];
            $this->identifyVars($content);
            $lines = explode("\n", $content);
            // Checking for minified HTML
            if (1 == count($lines)) {
                $content = str_replace('-->', "-->\n", $content);
                $lines = explode("\n", $content);
            }
            foreach (explode("\n", $content) as $line) {
                if (strpos($line, "<!--") !== false) $this->identifyBlocks($line, $varname, $queuedBlocks, $blocks);
            }

            return $blocks;
        }

        /**
         * Identify all user defined blocks automatically.
         *
         * @param string $line         Contains one line of the content file.
         * @param string $varname      Contains the filename variable identifier.
         * @param array  $queuedBlocks Contains a list of the current queued blocks.
         * @param string $blocks       Contains a list of all identified blocks in the current file.
         *
         * @return void
         */
        protected function identifyBlocks(&$line, $varname, &$queuedBlocks, &$blocks)
        {
            $reg = '/<!--\s*BEGIN\s+('.self::$REG_NAME.')\s*-->/sm';
            preg_match($reg, $line, $matches);
            if (1 == preg_match($reg, $line, $matches)) {
                if (0 == count($queuedBlocks)) $parent = $varname;
                else $parent = end($queuedBlocks);

                if (!isset($blocks[$parent])) $blocks[$parent] = [];

                $blocks[$parent][] = $matches[1];
                $queuedBlocks[]    = $matches[1];
            }
            $reg = '/<!--\s*END\s+('.self::$REG_NAME.')\s*-->/sm';
            if (1 == preg_match($reg, $line)) {
                array_pop($queuedBlocks);
            }
        }

        /**
         * Identifies all variables defined in the document.
         *
         * @param string $content File content.
         *
         * @return void
         */
        protected function identifyVars(&$content)
        {
            $result = preg_match_all(
                '/{('.self::$REG_NAME.')((\-\>('.self::$REG_NAME.'))*)?((\|.*?)*)?}/',
                $content,
                $matches
            );

            if ($result) {
                for ($i = 0; $i < $result; $i++) {
                    // Object var detected
                    if ($matches[3][$i] && (!isset($this->properties[$matches[1][$i]]) ||
                        !in_array($matches[3][$i], $this->properties[$matches[1][$i]]))
                    ) {
                        $this->properties[$matches[1][$i]][] = $matches[3][$i];
                    }
                    // Modifiers detected
                    if ($matches[7][$i] && (!isset($this->modifiers[$matches[1][$i]]) ||
                        !in_array($matches[7][$i], $this->modifiers[$matches[1][$i] . $matches[3][$i]]))
                    ) {
                        $this->modifiers[$matches[1][$i].$matches[3][$i]][]
                            = $matches[1][$i].$matches[3][$i].$matches[7][$i];
                    }
                    // Common variables
                    if (!in_array($matches[1][$i], $this->vars)) {
                        $this->vars[] = $matches[1][$i];
                    }
                }
            }
        }

        /**
         * Create all identified blocks given by Template::identifyBlocks().
         *
         * @param array $blocks Contains all identified block names.
         *
         * @return void
         *
         * @throws \UnexpectedValueException When block is declared more than one time.
         */
        protected function createBlocks(&$blocks)
        {
            $this->parents = array_merge($this->parents, $blocks);
            foreach ($blocks as $parent => $block) {
                foreach ($block as $chield) {
                    if (in_array($chield, $this->blocks)) {
                        throw new \UnexpectedValueException("duplicated block: $chield");
                    }

                    $this->blocks[] = $chield;
                    $this->setBlock($parent, $chield);
                }
            }
        }

        /**
         * A variable $parent may contain a variable block defined by:
         * &lt;!-- BEGIN $varname --&gt; content &lt;!-- END $varname --&gt;.
         *
         * This method removes that block from $parent and replaces it with a variable
         * reference named $block.
         * Blocks may be nested.
         *
         * @param string $parent Contains the name of the parent variable.
         * @param string $block  Contains the name of the block to be replaced.
         *
         * @return void
         *
         * @throws \UnexpectedValueException When $block is not found or malformed.
         */
        protected function setBlock($parent, $block)
        {
            $name = $block.'_value';
            $str  = $this->getVar($parent);
            if ($this->accurate) {
                $str = str_replace("\r\n", "\n", $str);
                $reg = "/\t*<!--\s*BEGIN\s+$block\s+-->\n*(\s*.*?\n?)\t*<!--\s+END\s+$block\s*-->\n*((\s*.*?\n?)\t*<!--\s+FINALLY\s+$block\s*-->\n?)?/sm";
            } else {
                $reg = "/<!--\s*BEGIN\s+$block\s+-->\s*(\s*.*?\s*)<!--\s+END\s+$block\s*-->\s*((\s*.*?\s*)<!--\s+FINALLY\s+$block\s*-->)?\s*/sm";
            }

            if (1 !== preg_match($reg, $str, $matches)) {
                throw new \UnexpectedValueException("mal-formed block $block");
            }

            $this->setValue($name, '');
            $this->setValue($block, $matches[1]);
            $this->setValue($parent, preg_replace($reg, '{'.$name.'}', $str));
            if (isset($matches[3])) {
                $this->finally[$block] = $matches[3];
            }
        }

        /**
         * Internal setValue() method.
         *
         * The main difference between this and Template::__set() method is this
         * method cannot be called by the user, and can be called using variables or
         * blocks as parameters.
         *
         * @param string $varname Contains the varname.
         * @param string $value   Contains the new value for the variable.
         *
         * @return void
         */
        protected function setValue($varname, $value)
        {
            $this->values['{'.$varname.'}'] = $value;
        }

        /**
         * Returns the value of the variable identified by $varname.
         *
         * @param string $varname The name of the variable to get the value of.
         *
         * @return string The value of the variable passed as argument.
         */
        protected function getVar($varname)
        {
            return $this->values['{'.$varname.'}'];
        }

        /**
         * Clear the value of a variable.
         *
         * Alias for $this->setValue($varname, "");
         *
         * @param string $varname Var name to be cleaned.
         *
         * @return void
         */
        public function clear($varname)
        {
            $this->setValue($varname, '');
        }

        /**
         * Manually assign a child block to a parent block.
         *
         * @param string $parent Parent block.
         * @param string $block  Child block.
         *
         * @return void
         */
        public function setParent($parent, $block)
        {
            $this->parents[$parent][] = $block;
        }

        /**
         * Subst modifiers content.
         *
         * @param string $value Text to be modified.
         * @param mixed  $exp   Expression to be searched.
         *
         * @return unknown_type
         */
        protected function substModifiers($value, $exp)
        {
            $statements = explode('|', $exp);
            $count      = count($statements);

            for ($i = 1; $i < $count; $i++) {
                $temp       = explode(':', $statements[$i]);
                $function   = $temp[0];
                $parameters = array_diff($temp, [$function]);
                $value      = call_user_func_array($function, array_merge([$value], $parameters));
            }

            return $value;
        }

        /**
         * Fill in all the variables contained in variable named $value.
         * $value. The resulting string is not "cleaned" yet.
         *
         * @param string $value Value.
         *
         * @return string content with all variables substituted.
         *
         * @throws \BadMethodCallException If $value not found in class.
         */
        protected function subst($value)
        {
            // Common variables replacement
            $string = str_replace(array_keys($this->values), $this->values, $value);
            // Common variables with modifiers
            foreach ($this->modifiers as $var => $expressions) {
                if (false !== strpos($string, '{'.$var.'|')) {
                    foreach ($expressions as $exp) {
                        if (false === strpos($var, '->') && isset($this->values['{'.$var.'}'])) {
                            $string = str_replace(
                                '{'.$exp.'}',
                                $this->substModifiers($this->values['{'.$var.'}'], $exp),
                                $string
                            );
                        }
                    }
                }
            }

            // Object variables replacement
            foreach ($this->instances as $var => $instance) {
                foreach ($this->properties[$var] as $properties) {
                    if (false !== strpos($string, '{'.$var.$properties.'}')
                        || false !== strpos($string, '{'.$var.$properties.'|')
                    ) {
                        $pointer  = $instance;
                        $property = explode('->', $properties);
                        $count    = count($property);

                        for ($i = 1; $i < $count; $i++) {
                            if ($pointer === null) {
                                $className = ($property[$i - 1] !== false) ? $property[$i - 1] : get_class($instance);
                                $class     = ($pointer === null) ? 'NULL' : get_class($pointer);

                                throw new \BadMethodCallException(
                                    "no accessor method in class $class for $className->$property[$i]"
                                );
                            }

                            $obj = strtolower(str_replace('_', '', $property[$i]));

                            if (method_exists($pointer, "get$obj")) { // Get accessor.
                                $pointer = $pointer->{"get$obj"}();
                            } else if (method_exists($pointer, '__get')) { // Magic __get accessor.
                                $pointer = $pointer->__get($property[$i]);
                            } else if (property_exists($pointer, $obj)) { // Property acessor.
                                $pointer = $pointer->$obj;
                            } else if (property_exists($pointer, $property[$i])) {
                                $pointer = $pointer->{$property[$i]};
                            } else {
                                $className = ($property[$i - 1] !== false) ? $property[$i - 1] : get_class($instance);
                                $class     = ($pointer === null) ? 'NULL' : get_class($pointer);

                                throw new \BadMethodCallException(
                                    "no accessor method in class $class for $className->$property[$i]"
                                );
                            }
                        }

                        $pointer_str = $pointer;
                        // Checking if final value is an object...
                        if (is_object($pointer)) {
                            $pointer_str = method_exists($pointer, '__toString')
                                ? $pointer->__toString() : json_encode($pointer);
                        } else if (is_array($pointer)) { // array?
                            $value = '';
                            $count = count($pointer);

                            for ($i = 0; $i < $count; $i++) {
                                $value .= [
                                    key($pointer),
                                    current($pointer),
                                ];

                                if ($i < count($pointer) - 1) $value .= ',';
                            }

                            $pointer_str = $value;
                        }
                        // Replacing value
                        $string = str_replace('{' . $var . $properties . '}', $pointer_str, $string);
                        // Object with modifiers
                        if (isset($this->modifiers[$var.$properties]))
                            foreach ($this->modifiers[$var.$properties] as $exp)
                                $string = str_replace('{'.$exp.'}', $this->substModifiers($pointer, $exp), $string);
                    }
                }
            }

            return $string;
        }

        /**
         * Show a block.
         *
         * This method must be called when a block must be showed.
         * Otherwise, the block will not appear in the resultant
         * content.
         *
         * @param string  $block  The block name to be parsed.
         * @param boolean $append True if the content must be appended.
         *
         * @return void
         *
         * @throws \InvalidArgumentException When the block does not exist.
         */
        public function block($block, $append=true)
        {
            if (!in_array($block, $this->blocks)) throw new \InvalidArgumentException("block $block does not exist");

            // Checking finally blocks inside this block
            if (isset($this->parents[$block]))
                foreach ($this->parents[$block] as $child)
                    if (isset($this->finally[$child]) && !in_array($child, $this->parsed)) {
                        $this->setValue($child.'_value', $this->subst($this->finally[$child]));
                        $this->parsed[] = $block;
                    }

            if ($append) $this->setValue(
                $block.'_value',
                $this->getVar($block.'_value').$this->subst($this->getVar($block))
            );
            else $this->setValue($block . '_value', $this->getVar($block.'_value'));

            if (!in_array($block, $this->parsed)) $this->parsed[] = $block;

            // Cleaning children
            if (isset($this->parents[$block]))
                foreach ($this->parents[$block] as $child)
                    $this->clear($child . '_value');
        }

        /**
         * Returns the final content
         *
         * @return string The final content.
         */
        public function parse()
        {
            // Auto assistance for parse children blocks
            foreach (array_reverse($this->parents) as $parent => $children)
                foreach ($children as $block)
                    if (in_array($parent, $this->blocks)
                        && in_array($block, $this->parsed)
                        && !in_array($parent, $this->parsed)
                    ) {
                        $this->setValue($parent.'_value', $this->subst($this->getVar($parent)));
                        $this->parsed[] = $parent;
                    }

            // Parsing finally blocks
            foreach ($this->finally as $block => $content)
                if (!in_array($block, $this->parsed)) $this->setValue($block.'_value', $this->subst($content));

            // After subst, remove empty vars
            return preg_replace(
                '/{('.self::$REG_NAME.')((\-\>('.self::$REG_NAME.'))*)?((\|.*?)*)?}/',
                '',
                $this->subst($this->getVar('.'))
            );
        }

        /**
         * Print the final content.
         *
         * @return void
         */
        public function show()
        {
            echo $this->parse();
        }

    }

}

namespace {

    /**
     * Suitable for Template class: similar to str_replace, but using string in first param
     *
     * @param string $str     The string to replace.
     * @param string $search  The search string.
     * @param string $replace The replacement string.
     *
     * @return mixed The replaced string.
     *
     * @see str_replace
     */
    function replace($str, $search, $replace)
    {
        return str_replace($search, $replace, $str);
    }

}
