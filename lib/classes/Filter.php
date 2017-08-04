<?php

/**
 * Classe per gestire la sanitarizzazione degli input, basata sul framework open source HTMLPurifier.
 *
 * @since 2.3
 */
class Filter
{
    /** @var HTMLPurifier */
    protected static $purifier;

    /** @var array Lista dei contenuti inviati via POST */
    protected static $post;
    /** @var array Lista dei contenuti inviati via GET */
    protected static $get;

    /**
     * Restituisce il valore presente nei dati ottenuti dall'input dell'utente.
     *
     * @param string $property
     * @param string $method
     *
     * @return string
     */
    public static function getValue($property, $method = null)
    {
        $value = null;

        if (empty($method)) {
            $value = (self::post($property) !== null) ? self::post($property) : self::get($property);
        } elseif (strtolower($method) == 'post') {
            $value = self::post($property);
        } elseif (strtolower($method) == 'get') {
            $value = self::get($property);
        }

        return $value;
    }

    /**
     * Restituisce i contenuti dalla sezione POST.
     *
     * @return array
     */
    public static function getPOST()
    {
        if (empty(self::$post)) {
            self::$post = self::sanitize($_POST);
        }

        return self::$post;
    }

    /**
     * Restituisce il valore presente nei dati ottenuti dalla sezione POST.
     *
     * @param string $property
     *
     * @return string
     */
    public static function post($property)
    {
        if (!empty(self::getPOST()) && isset(self::getPOST()[$property])) {
            return self::getPOST()[$property];
        }
    }

    /**
     * Restituisce i contenuti dalla sezione GET.
     *
     * @return array
     */
    public static function getGET()
    {
        if (empty(self::$get)) {
            self::$get = self::sanitize($_GET);
        }

        return self::$get;
    }

    /**
     * Restituisce il valore presente nei dati ottenuti dalla sezione GET.
     *
     * @param string $property
     *
     * @return string
     */
    public static function get($property)
    {
        if (!empty(self::getGET()) && isset(self::getGET()[$property])) {
            return self::getGET()[$property];
        }
    }

    /**
     * Sanitarizza il testo inserito.
     *
     * @param mixed $input Testo da sanitarizzare
     *
     * @return mixed
     */
    public static function sanitize($input)
    {
        $output = null;
        if (is_array($input)) {
            foreach ($input as $key => $value) {
                $output[$key] = self::sanitize($value);
            }
        } else {
            $output = trim(self::getPurifier()->purify($input));

            if (!empty($output) && !empty(Translator::getLocaleFormatter())) {
                if (Translator::getLocaleFormatter()->isNumber($output)) {
                    $output = Translator::numberToEnglish($output);
                } elseif (Translator::getLocaleFormatter()->isTimestamp($output)) {
                    $output = Translator::timestampToEnglish($output);
                } elseif (Translator::getLocaleFormatter()->isDate($output)) {
                    $output = Translator::dateToEnglish($output);
                } elseif (Translator::getLocaleFormatter()->isTime($output)) {
                    $output = Translator::timeToEnglish($output);
                }
            }
        }

        return $output;
    }

    /**
     * Restituisce l'istanza di HTMLPurifier in utilizzo.
     *
     * @return \HTMLPurifier
     */
    public static function getPurifier()
    {
        if (empty(self::$purifier)) {
            $config = \HTMLPurifier_Config::createDefault();
            $config->set('HTML.Allowed', 'a[href|target|title],img[class|src|border|alt|title|hspace|vspace|width|height|align|name],hr[class|width|size|noshade],font[face|size|color|style],span[class|style],br,p[class]');
            //$config->set('Cache.SerializerPath', realpath(__DIR__.'/cache/HTMLPurifier'));
            $config->set('Cache.DefinitionImpl', null);

            self::$purifier = new \HTMLPurifier($config);
        }

        return self::$purifier;
    }
}
