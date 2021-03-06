<?php

/**
 * Helpers that don't really belong elsewhere (naww)
 */
class XDT_CLI_Helper
{

    public static function writeToFile($file, $contents, $createPath = false)
    {
        $config 	= XDT_CLI_Application::getConfig();
        $filePath	= dirname($file);

        if ( ! is_dir($filePath) AND $createPath)
        {
            mkdir($filePath, octdec($config->dir_mask), true);
        }

        if ( ! is_dir($filePath) OR ! file_put_contents($file, trim($contents)))
        {
            XDT_CLI_Abstract::getInstance()->bail("File could not be created: " . $file);
            return false;
        }

        chmod($file, octdec($config['file_mask']));

        return true;
    }

    /**
     * Merge 2 objects (basically array_merge_recursive for objects)
     *
     * @param	Object			$ob1
     * @param	Object			$ob2
     *
     * @return	object
     */
    public static function objectMerge($ob1, $ob2)
    {
        $result = self::arrayMerge(self::convertToArray($ob1), self::convertToArray($ob2));

        return json_decode(json_encode($result)); // convert it back to an object
    }

    /**
     * Merge arrays recursively
     *
     * Credits: http://ca.php.net/manual/en/function.array-merge-recursive.php#104145
     *
     * @return	array
     */
    public static function arrayMerge()
    {
        if (func_num_args() < 2)
        {
            trigger_error(__FUNCTION__ .' needs two or more array arguments', E_USER_WARNING);
            return;
        }

        $arrays = func_get_args();
        $merged = array();

        while ($arrays)
        {
            $array = array_shift($arrays);

            if ( ! is_array($array))
            {
                trigger_error(__FUNCTION__ .' encountered a non array argument', E_USER_WARNING);
                return;
            }

            if ( ! $array)
            {
                continue;
            }

            foreach ($array as $key => $value)
            {

                if (is_string($key))
                {

                    if (is_array($value) && array_key_exists($key, $merged) && is_array($merged[$key]))
                    {
                        $merged[$key] = self::arrayMerge($merged[$key], $value);
                    }
                    else
                    {
                        $merged[$key] = $value;
                    }

                }
                else
                {
                    $merged[] = $value;
                }

            }
        }

        return $merged;
    }

    /**
     * Convert an object to an array recursively
     *
     * @param	Object			$ob
     *
     * @return	mixed		If input was neither an object nor an array it returns the original input
     */
    public static function convertToArray($ob)
    {
        if ( ! is_array($ob) AND ! is_object($ob))
        {
            return $ob;
        }

        foreach ($ob AS $k => &$v)
        {
            if (is_array($v) OR is_object($v))
            {
                $v = self::convertToArray($v);
            }
        }

        return (array) $ob;
    }

    /**
     * Json encode string and make it human readable
     *
     * Credits: http://snipplr.com/view/60559/prettyjson/
     *
     * @param	mixed			$input
     *
     * @return	string
     */
    public static function jsonEncode($input)
    {

        $json 		 = json_encode($input);
        $json 		 = str_replace('\\/', '/', $json); // json_encode escapes forward slashes for whatever reason
        $result      = '';
        $pos         = 0;
        $strLen      = strlen($json);
        $indentStr   = '	';
        $newLine     = "\n";
        $prevChar    = '';
        $outOfQuotes = true;

        for ($i=0; $i<=$strLen; $i++)
        {

            // Grab the next character in the string.
            $char = substr($json, $i, 1);

            // Are we inside a quoted string?
            if ($char == '"' AND $prevChar != '\\')
            {
                $outOfQuotes = !$outOfQuotes;
            }

            // If this character is the end of an element,
            // output a new line and indent the next line.
            else if(($char == '}' || $char == ']') AND $outOfQuotes)
            {
                $result .= $newLine;
                $pos --;
                for ($j=0; $j<$pos; $j++) {
                    $result .= $indentStr;
                }
            }

            // Add the character to the result string.
            $result .= $char;

            if ($char == ':' AND $outOfQuotes)
            {
                $result .= $indentStr;
            }

            // If the last character was the beginning of an element,
            // output a new line and indent the next line.
            if (($char == ',' || $char == '{' || $char == '[') AND $outOfQuotes)
            {
                $result .= $newLine;
                if ($char == '{' || $char == '[')
                {
                    $pos ++;
                }

                for ($j = 0; $j < $pos; $j++)
                {
                    $result .= $indentStr;
                }
            }

            $prevChar = $char;
        }

        return $result;

    }

    /**
     * Convert string to camelcase
     *
     * @param	string			$string
     * @param	bool			$lowerFirst
     *
     * @return	string
     */
    public static function camelcaseString($string, $lowerFirst = true)
    {
        $string = preg_replace('/[^a-z0-9]/i', '', ucwords(strtolower($string)));

        if ($lowerFirst)
        {
            $string = lcfirst($string);
        }

        return $string;
    }

    /**
     * Search for the given file in the cwd and the given folder variations
     *
     * @param	string			$file
     * @param	array			$folders
     * @param	string			$strip
     * @param	array			$ignoreFolders
     *
     * @return	string|bool
     */
    public static function locate($file, array $folders = array(), $strip = null, array $ignoreFolders = array())
    {
        // Variable shortcuts
        $ds 	= DIRECTORY_SEPARATOR;
        $cwd 	= getcwd() . $ds;
        $up 	= '..' . $ds;

        // Set default variations
        $variations = array($file, "", $up, $up . $up);

        // Append given folder variations
        foreach ($folders AS $folder)
        {
            $folder = str_replace('/', $ds, $folder); // Windows compatibility

            $variations[] = $folder;
            $variations[] = $up . $folder;
            $variations[] = $up . $up . $folder;
        }

        // Prepend cwd if the file is not an absolute path
        if (substr($file, 0, 1) != DIRECTORY_SEPARATOR)
        {
            $v = $variations;
            for ($c=count($v)-1;$c>=0; $c--)
            {
                array_unshift($variations, $cwd . $v[$c]);
            }
        }

        // Add realpath values for ignored folder
        foreach ($ignoreFolders AS $ignore)
        {
            $ignoreFolders[] = realpath($ignore);
        }

        // iterate through variations and check for matches
        foreach ($variations AS $variation)
        {
            $base = dirname($variation . $ds . $file);

            // Check if this variation should be ignored
            if (in_array($base, $ignoreFolders) OR in_array(realpath($base), $ignoreFolders))
            {
                continue;
            }

            // Check if we have a match
            if (file_exists($variation . $ds . $file))
            {
                $result = realpath($variation . $ds . $file);

                // Check if we need to strip the given prefix
                if ($strip != null AND strpos($result, $strip) === 0)
                {
                    $result = substr($result, strlen($strip));
                }

                return $result;
            }
        }

        return false;
    }

    /**
     * Check if given class exists and creates dummy XFCP class to prevent errors if necessary
     *
     * @param	string			$className
     * @param	bool			$createXfcp
     * @param 	bool 			$alias
     *
     * @return	bool
     */
    public static function classExists($className, $createXfcp = true, $alias = true)
    {
        if ($createXfcp)
        {
            $xfcpClass = 'XFCP_' . $className;

            if (!XDT_CLI_Helper::classExists($xfcpClass, false, false))
            {
                eval("class $xfcpClass {}");
            }
        }

        if ($alias)
        {
            $className = XDT_CLI_Helper::loadClassAliased($className);
        }

        try // Workaround XF's Autoloader that doesn't play nice
        {
            return class_exists($className);
        } catch (Exception $e) {}

        return false;
    }

    /**
     * Load class under an alias, so that it can be modified without invalidating the class namespace for this session
     *
     * @param		string			$className
     * @param		string|null		$alias
     *
     * @return		bool|string
     */
    public static function loadClassAliased($className, $alias = null)
    {
        if ($alias == null)
        {
            $alias = $className . '_alias_' . uniqid();
        }

        $path = XDT_CLI_Autoloader::getClassPath($className);

        if ( ! $path)
        {
            return false;
        }

        $contents = file_get_contents($path);
        $contents = preg_replace('/(.*class)\s*?'.$className.'/', '$1 ' . $alias, $contents);

        $filePath = tempnam(sys_get_temp_dir(), 'xfcli');
        XDT_CLI_Helper::writeToFile($filePath, $contents);

        require_once $filePath;

        return $alias;
    }

}