<?php

/**
 * ImgExif class file
 *
 * @author tonylevid <tonylevid@gmail.com>
 * @link https://github.com/tonylevid/ImgExif
 * @copyright http://tonylevid.com/
 */

class ImgExif {

    /**
     * Current loaded files.
     */
    public $files;

    /**
     * ImgImagick class instance.
     */
    protected $_im;

    /**
     * Constructor.
     * @param mixed $files The path to an image or an array of paths, defaults to null.
     * @return void
     */
    public function __construct($files = null) {
        if (!extension_loaded('imagick')) {
            throw new Exception('ImgExif requires imagick extension loaded.');
        }
        $this->files = $files;
    }

    /**
     * Get exif information.
     * Documentation for exif: http://www.cipa.jp/english/hyoujunka/kikaku/pdf/DC-008-2010_E.pdf
     * @param array $items Filter of the exif items, defaults to array().
     * @param string $lang Lang name. If empty array, it will return original exif information. If its value indicates empty except array(), it will be the default lang name 'zh_cn'.
     * @param string $dir Lang file folder. If empty, it will be 'lang' folder under current folder.
     * @return array Exif information.
     */
    public function getExif($items = array(), $lang = null, $dir = null) {
        $isItemsAStr = false;
        if (is_string($items) && !empty($items)) {
            $isItemsAStr = true;
            $items = array($items);
        }
        if (is_array($lang) && empty($lang)) {
            $langArr = array();
        } else {
            $langArr = $this->getLang($lang, $dir);
        }
        $exifArr = $this->getImagick()->getImageProperties('exif:*');
        if (is_array($items) && !empty($items)) {
            $getExifArr = array();
            foreach ($items as $item) {
                $item = 'exif:' . $item;
                if (array_key_exists($item, $exifArr)) {
                    $getExifArr[$item] = $exifArr[$item];
                }
            }
            $exifArr = $getExifArr;
        }
        $translatedArr = array();
        foreach ($exifArr as $k => $v) {
            $arr = explode(':', $k); // array('exif', 'exifName')
            $exifKey = $arr[1];
            if (array_key_exists($exifKey, $langArr)) {
                if (is_array($langArr[$exifKey])) {
                    $translatedKey = isset($langArr[$exifKey]['__translation__']) ? $langArr[$exifKey]['__translation__'] : $exifKey;
                    $translatedValue = isset($langArr[$exifKey][$v]) ? $langArr[$exifKey][$v] : $v;
                    if (isset($langArr[$exifKey]['__isArithmetic__']) && $langArr[$exifKey]['__isArithmetic__']) {
                        $decimals = isset($langArr[$exifKey]['__decimals__']) ? $langArr[$exifKey]['__decimals__'] : 0;
                        ob_start();
                        $evalStr = 'return (' . $translatedValue . ');';
                        $num = eval($evalStr);
                        $errMsg = ob_get_clean();
                        if (stripos($errMsg, 'error') !== false) {
                            $translatedValue = '{ERROR: NOT A ARITHMETIC}';
                        } else {
                            $translatedValue = floatval(number_format($num, $decimals));
                        }
                    }
                    if (isset($langArr[$exifKey]['__unit__'])) {
                        $unit = $langArr[$exifKey]['__unit__'];
                        if (preg_match('/\{\{([A-Za-z0-9_-]+)\}\}/', $unit, $matches)) {
                            if (isset($matches[1])) {
                                $unitExifKey = $matches[1];
                                $unitExifValue = isset($exifArr['exif:' . $unitExifKey]) ? $exifArr['exif:' . $unitExifKey] : '';
                                $unitTranslatedValue = $langArr[$unitExifKey][$unitExifValue];
                                if (!empty($unitExifValue)) {
                                    $unit = preg_replace('/(.*)(\{\{[A-Za-z0-9_-]+\}\})(.*)/', '$1' . $unitTranslatedValue . '$3', $unit);
                                }
                            }
                        }
                        $translatedValue .= $unit;
                    }
                } else {
                    $translatedKey = $langArr[$exifKey];
                    $translatedValue = $v;
                }
            } else {
                $translatedKey = $exifKey;
                $translatedValue = $v;
            }
            $translatedArr[$translatedKey] = $translatedValue;
        }
        if ($isItemsAStr) {
            return array_pop($translatedArr);
        }
        return $translatedArr;
    }

    /**
     * Get exif original information.
     * @return array Exif original array.
     */
    public function getExifOriginal() {
        return $this->getExif(array(), array());
    }

    /**
     * Get ImgImagick class instance.
     * @param mixed $files The path to an image or an array of paths, defaults to null.
     * @return object ImgImagick class instance.
     */
    protected function getImagick($files = null) {
        if (!empty($files)) {
            $this->files = $files;
        }
        if (!$this->_im instanceof Imagick) {
            $this->_im = new Imagick($this->files);
        }
        return $this->_im;
    }

    /**
     * Get language array.
     * @param string $lang Lang name. If empty, it will be the default lang name 'zh_cn'.
     * @param string $dir Lang file folder. If empty, it will be 'lang' folder under current folder.
     * @return array Language array.
     */
    protected function getLang($lang = null, $dir = null) {
        $langArr = array();
        empty($lang) && ($lang = 'zh_cn');
        empty($dir) && ($dir = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'lang');
        $langfile = $dir . DIRECTORY_SEPARATOR . $lang . '.php';
        if (is_file($langfile)) {
            $langArr = include $langfile;
        }
        return $langArr;
    }

}