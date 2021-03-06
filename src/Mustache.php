<?php

namespace Simplon\Mustache;

/**
 * Mustache
 * @package Simplon\Mustache
 * @author Tino Ehrich (tino@bigpun.me)
 */
class Mustache
{
    /**
     * @var array
     */
    private static $data;

    /**
     * @var array
     */
    private static $templates = [];

    /**
     * @param $template
     * @param array $data
     * @param array $customParsers
     *
     * @return string
     */
    public static function render($template, array $data = [], array $customParsers = [])
    {
        // cache data
        self::$data = $data;

        // parse template
        $template = self::parse($template, $data);

        // run custom parsers
        $template = self::handleCustomParsers($template, $customParsers);

        return $template;
    }

    /**
     * @param $pathTemplate
     * @param array $data
     * @param array $customParsers
     * @param string $fileExtension
     *
     * @return string
     * @throws MustacheException
     */
    public static function renderByFile($pathTemplate, array $data = [], array $customParsers = [], $fileExtension = 'mustache')
    {
        // set filename
        $fileName = $pathTemplate . '.' . $fileExtension;

        // test cache
        if (isset(self::$templates[$pathTemplate]) === false)
        {
            // make sure the file exists
            if (file_exists($fileName) === false)
            {
                throw new MustacheException('Missing given template file: ' . $fileName);
            }

            // fetch template
            $template = file_get_contents($fileName);

            if ($template === false)
            {
                throw new MustacheException('Could not load template file: ' . $fileName);
            }

            // cache template
            self::$templates[$pathTemplate] = $template;
        }

        return self::render(self::$templates[$pathTemplate], $data, $customParsers);
    }

    /**
     * @param string $template
     *
     * @return string
     */
    public static function cleanTemplate($template)
    {
        // remove left over wrappers
        $template = preg_replace('|{{.*?}}.*?{{/.*?}}\n*|s', '', $template);

        // remove left over variables
        $template = preg_replace('|{{.*?}}\n*|s', '', $template);

        return (string)$template;
    }

    /**
     * @param $template
     * @param array $data
     *
     * @return string
     */
    private static function parse($template, array $data = [])
    {
        foreach ($data as $key => $val)
        {
            if (is_array($val) && empty($val) === false)
            {
                // find loops with all newsline symbols
                preg_match_all('|{{#' . $key . '}}(.*?){{/' . $key . '}}\n*|sm', $template, $foreachPattern);

                // handle loops
                if (isset($foreachPattern[1][0]))
                {
                    foreach ($foreachPattern[1] as $patternId => $patternContext)
                    {
                        $loopContent = '';

                        // handle array objects
                        if (isset($val[0]))
                        {
                            foreach ($val as $loopVal)
                            {
                                // make simple lists available
                                if (is_array($loopVal) === false)
                                {
                                    $loopVal = ['_' => $loopVal];
                                }

                                // iterate through two-dimensional lists
                                if (is_array($loopVal) === true && isset($loopVal[0]))
                                {
                                    $loopVal = ['_' => $loopVal];
                                }

                                // trim the loop content and add a new line symbol for plain text
                                $loopContent .= self::parse(preg_replace('/(^\s*|\n$)/u', '', $patternContext) . "\n", $loopVal);
                            }
                        }

                        // normal array only
                        else
                        {
                            $loopContent = self::parse($patternContext, $val);
                        }

                        // replace pattern context
                        $template = preg_replace(
                            '|' . preg_quote($foreachPattern[0][$patternId]) . '|s',
                            $loopContent . "\n", // plain text gets one empty line after loop
                            $template,
                            1
                        );
                    }
                }
            }

            else if (is_array($val) && empty($val) === true)
            {
                // remove
                $template = preg_replace('|{{#' . $key . '}}(.*?){{/' . $key . '}}\n*|sm', '', $template);
            }

            // ----------------------------------

            elseif (is_bool($val) || is_array($val) && empty($val) === true)
            {
                // determine conditional char
                $conditionChar = $val === true ? '\#' : '\^';
                $negationChar = $val === true ? '\^' : '\#';

                // remove bools
                $template = preg_replace('|{{' . $negationChar . $key . '}}.*?{{/' . $key . '}}\n*|s', '', $template);

                // find bools
                preg_match_all('|{{' . $conditionChar . $key . '}}(.*?){{/' . $key . '}}|s', $template, $boolPattern);

                // handle bools
                if (isset($boolPattern[1][0]))
                {
                    foreach ($boolPattern[1] as $patternId => $patternContext)
                    {
                        // parse and replace pattern context
                        $template = preg_replace(
                            '|' . preg_quote($boolPattern[0][$patternId]) . '|s',
                            self::parse($patternContext, self::$data),
                            $template,
                            1
                        );
                    }
                }
            }

            // ----------------------------------

            elseif ($val instanceof \Closure)
            {
                // set closure return
                $template = str_replace('{{{' . $key . '}}}', $val(), $template);
                $template = str_replace('{{' . $key . '}}', htmlspecialchars($val()), $template);
            }

            // ----------------------------------

            elseif (gettype($val) !== 'object')
            {
                // set var: unescaped
                $template = str_replace('{{{' . $key . '}}}', $val, $template);

                // set var: escaped
                $template = str_replace('{{' . $key . '}}', htmlspecialchars($val), $template);
            }
        }

        return (string)$template;
    }

    /**
     * @param string $template
     * @param array $parsers
     *
     * @return string
     */
    private static function handleCustomParsers($template, array $parsers = [])
    {
        foreach ($parsers as $parser)
        {
            if (isset($parser['pattern']) && isset($parser['callback']))
            {
                preg_match_all('|' . $parser['pattern'] . '|', $template, $match);

                if (isset($match[1][0]))
                {
                    $template = $parser['callback']($template, $match);
                }
            }
        }

        return (string)$template;
    }
}