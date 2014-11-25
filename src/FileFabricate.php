<?php

/**
 * Class FileFabricate
 *
 * テスト用にファイルを簡単に出力するためのツール。
 * テスト後に(register_shutdown_functionで)自動的にファイルは削除される。
 */
class FileFabricate {
    private static $base_str = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";

    public static $dir = null;

    public static function from2DimensionalArray($data) {
        return new FileFabricateDataCells($data);
    }

    public static function fromString($string) {
        return new FileFabricateFile($string);
    }

    public static function defineTemplate($definition) {
        return new FileFabricateTemplate($definition);
    }

    public static function value_integer($max = null) {
        return self::value_callback(function ($i) use ($max) {
            if ($max === null) {
                $row = $i + 1;
            } else {
                $row = $i % $max + 1; //maxが指定されたらmax内をループする
            }
            return $row;
        });
    }

    public static function value_string($size) {
        $base = self::$base_str;
        return self::value_callback(function ($i) use ($size, $base) {
            $pos = $i % strlen($base);
            return str_repeat($base[$pos], $size);
        });
    }

    public static function value_date($format = 'Y-m-d H:i:s', $basedate = '2000-01-01 00:00:00') {
        return self::value_callback(function ($i) use ($format, $basedate) {
            return date($format, strtotime($basedate) + $i * 3600 * 24);
        });
    }

    public static function value_rotation($array) {
        return self::value_callback(function ($i) use ($array) {
            $pos = $i % count($array);
            return $array[$pos];
        });
    }

    public static function value_callback($callback) {
        return new FileFabricateFabricatorByLabel($callback);
    }
}


class FileFabricateFileSettings {
    public $encodeTo = 'UTF-8';
    public $bom = null;
    public $directory = null;
    public $filename = null;
    public $alreadyMade = false;
}

trait FileFabricateFileSettingsSetter {
    /** @var FileFabricateFileSettings */
    protected $settings;

    private function __initFileSettings() {
        $this->settings = new FileFabricateFileSettings();
    }

    public function encodeTo($encoding) {
        $this->settings->encodeTo = $encoding;
        $this->__resetFile();
        return $this;
    }

    public function prependUtf8Bom() {
        $this->settings->bom = "\xef\xbb\xbf";
        $this->__resetFile();
        return $this;
    }

    public function moveDirectoryTo($directory) {
        $this->settings->directory = $directory;
        $this->__resetFile();
        return $this;
    }

    public function changeFileNameTo($filename) {
        $this->settings->filename = $filename;
        $this->__resetFile();
        return $this;
    }

    private function __resetFile() {
        $this->settings->alreadyMade = false;
    }
}


/**
 * Class FileFabricateDataCells
 *
 * csv/tsv を作成できる２次元データ
 */
class FileFabricateDataCells {
    private $cells = null; //２次元配列

    private $withoutBrAtEof = false;

    private $changes = [];

    public function __construct($cells) {
        $this->cells = $cells;
    }

    public function toCsv($delimiter = ',', $enclosure = '"') {
        $_this = $this;
        return new FileFabricateFile(function () use ($_this, $delimiter, $enclosure) {
            if (!empty($this->changes)) {
                foreach ($this->changes as $change) {
                    list($i, $col, $value) = $change;
                    $_this->cells[$i][$col] = $value;
                }
            }
            $fh_memory = fopen("php://memory", "rw");
            foreach ($_this->cells as $row) {
                fputcsv($fh_memory, $row, $delimiter, $enclosure);
            }
            $size = ftell($fh_memory);
            fseek($fh_memory, 0);
            $str = fread($fh_memory, $size);
            fclose($fh_memory);
            if ($this->withoutBrAtEof) {
                $str = preg_replace('/\n$/', "", $str);
            }
            return $str;
        });
    }

    public function toTsv($delimiter = "\t", $enclosure = '"') {
        return $this->toCsv($delimiter, $enclosure);
    }

    /**
     * ファイル末尾には末尾の改行コードをつけない。
     *
     * @return $this
     */
    public function withoutBrAtEof() {
        $this->withoutBrAtEof = true;
        return $this;
    }

    public function changeValue($i, $label, $value) {
        $col = array_search($label, $this->cells[0]);
        if ($col === FALSE) {
            throw new LogicException("Unkown Label: " . $label);
        }
        if (count($this->cells) < $i) {
            throw new LogicException("More than the number of rows (" . count($this->cells) . ") : " . $i);
        }

        $this->changes[] = [$i, $col, $value];

        return $this;
    }
}

/**
 * Class FileFabricateFile
 *
 * ファイル出力可能な文字列データ
 */
class FileFabricateFile {
    use FileFabricateFileSettingsSetter;

    /** @var string|callable */
    private $data; //２次元配列

    private $path = null; //作成済みのファイルパス

    /**
     * @param string|callable $data
     */
    public function __construct($data) {
        $this->data = $data;
        $this->__initFileSettings();
    }

    private function makeFileIfNotExist() {
        //作成済みなら省略
        if (!empty($this->path)) {
            return;
        }
        //ファイルパス生成
        $path = $this->__tempnum();

        //$dataをファイルに出力
        $fh = fopen($path, 'w');
        try {

            //BOM
            if ($this->settings->bom !== null) {
                fwrite($fh, $this->settings->bom);
            }

            //データ取得
            $str = $this->data;
            if (is_callable($str)) {
                $str = $str();
            }

            //文字コード変換
            if ($this->settings->encodeTo !== "UTF-8") {
                $str = mb_convert_encoding($str, $this->settings->encodeTo, "UTF-8");
            }

            fwrite($fh, $str);

        } catch (Exception $e) {
            fclose($fh); //mb_convert_encoding 等でエラーになっても確実にストリームを閉じる
            throw $e;
        }
        fclose($fh);

        $this->path = $path;
    }

    /**
     * @return string
     */
    public function getPath() {
        $this->makeFileIfNotExist();
        return $this->path;
    }

    private function __tempnum() {
        if (!empty($this->directory)) {
            $TMP_DIR = $this->directory;
        } elseif (!empty(FileFabricate::$dir)) {
            $TMP_DIR = FileFabricate::$dir;
        } else {
            $TMP_DIR = sys_get_temp_dir();
        }

        $path = tempnam($TMP_DIR, "tst");
        $this->__register_shutdown_remove_file($path);
        //CakeLog::write(LOG_DEBUG, "[tempfile create] " . $path);

        if (!empty($this->filename)) {
            $path2 = $TMP_DIR . "/" . $this->filename;
            if (file_exists($path2)) {
                throw new LogicException("Already exists: " . $path2);
            }
            rename($path, $path2);
            $this->__register_shutdown_remove_file($path2);
            $path = $path2;
        }

        return $path;
    }

    private function __register_shutdown_remove_file($path) {
        register_shutdown_function(function () use ($path) {
            if (file_exists($path)) {
                unlink($path);
                //CakeLog::write(LOG_DEBUG, "[tempfile remove] " . $path);
            }
        });
    }
}


class FileFabricateTemplate {
    /** @var FileFabricateFabricatorByLabel[]|mixed */
    private $definition = null;

    public function __construct($definition) {
        $this->definition = $definition;
    }

    public function rows($count) {
        $data = [];
        //ラベル出力
        $data[] = array_keys($this->definition);
        //値出力
        for ($i = 0; $i < $count; $i++) {
            $line = [];
            foreach ($this->definition as $label => $value) {
                if (is_array($value)) {
                    $value = FileFabricate::value_rotation($value);
                }
                if ($value instanceof FileFabricateFabricatorByLabel) {
                    $value = $value->_getValue($i);
                }
                $line[] = $value;
            }
            $data[] = $line;
        }
        return new FileFabricateDataCells($data);
    }
}


class FileFabricateFabricatorByLabel {
    /** @var callable */
    private $callback = null;

    private $format = null;

    public function __construct($callback) {
        $this->callback = $callback;
    }

    public function format($format = '%s') {
        $this->format = $format;
        return $this;
    }

    public function _getValue($i) {
        $callback = $this->callback;
        $value = $callback($i);
        if ($this->format !== null) {
            $value = sprintf($this->format, $value);
        }
        return $value;
    }
}