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

    public static function fromCsv($path, $delimiter = ',', $enclosure = '"') {
        $data = [];
        $fh = fopen($path, "r");
        $maxsize = filesize($path);
        while (($line = fgetcsv($fh, $maxsize, $delimiter, $enclosure)) !== FALSE) {
            $data[] = $line;
        }
        return new FileFabricateDataCells($data);
    }

    public static function defineTemplate($definition) {
        return new FileFabricateTemplate($definition);
    }

    public static function value_integer($min = 1, $max = null) {
        return self::value_callback(function ($i) use ($min, $max) {
            if ($max === null) {
                $row = $i + $min;
            } else {
                $row = $i % ($max - $min + 1) + $min; //maxが指定されたらmax内をループする
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

/**
 * Class FileFabricateDataCells
 *
 * csv/tsv を作成できる２次元データ
 */
class FileFabricateDataCells {
    private $cells; //２次元配列

    private $withoutBrAtEof = false;
    private $changes = [];
    private $delimiter;
    private $enclosure;

    public function __construct($cells) {
        $this->cells = $cells;
    }

    public function toCsv($delimiter = ',', $enclosure = '"') {
        $this->delimiter = $delimiter;
        $this->enclosure = $enclosure;
        return new FileFabricateFile($this);
    }

    public function __toString() {
        if (!empty($this->changes)) {
            foreach ($this->changes as $change) {
                list($i, $col, $value) = $change;
                $this->cells[$i][$col] = $value;
            }
        }
        $fh_memory = fopen("php://memory", "rw");
        foreach ($this->cells as $row) {
            fputcsv($fh_memory, $row, $this->delimiter, $this->enclosure);
        }
        $size = ftell($fh_memory);
        fseek($fh_memory, 0);
        $str = fread($fh_memory, $size);
        fclose($fh_memory);
        if ($this->withoutBrAtEof) {
            $str = preg_replace('/\n$/', "", $str);
        }
        return $str;
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

class FileFabricateFileSettings {
    public $encodeTo = 'UTF-8';
    public $bom = null;
    public $directory = null;
    public $filename = null;
    public $alreadyMade = false;
}

/**
 * Class FileFabricateFile
 *
 * ファイル出力可能な文字列データ
 */
class FileFabricateFile {

    /** @var string|FileFabricateDataCells */
    private $data;

    /** @var FileFabricateFileSettings */
    protected $settings;

    private $path = null; //作成済みのファイルパス

    /**
     * @param string|FileFabricateDataCells $data
     */
    public function __construct($data) {
        $this->settings = new FileFabricateFileSettings();
        $this->data = $data;
    }

    public function encodeTo($encoding) {
        $this->settings->encodeTo = $encoding;
        if ($encoding === 'UTF-16LE') {
            $this->settings->bom = "\xff\xfe";
        }
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

    public function changeValue($i, $label, $value) {
        if ($this->data instanceof FileFabricateDataCells) {
            $this->data->changeValue($i, $label, $value);
        } else {
            throw new LogicException("Cannot changeValue if not FileFabricateDataCells.");
        }
        $this->__resetFile();
        return $this;
    }

    private function __resetFile() {
        $this->settings->alreadyMade = false;
    }

    private function makeFileIfNotExist() {
        //作成済みなら省略
        if ($this->settings->alreadyMade) {
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
            if ($str instanceof FileFabricateDataCells) {
                $str = $str->__toString();
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
        if (!empty($this->settings->directory)) {
            $TMP_DIR = $this->settings->directory;
        } elseif (!empty(FileFabricate::$dir)) {
            $TMP_DIR = FileFabricate::$dir;
        } else {
            $TMP_DIR = sys_get_temp_dir();
        }

        $path = tempnam($TMP_DIR, "tst");
        $this->__register_shutdown_remove_file($path);
        //CakeLog::write(LOG_DEBUG, "[tempfile create] " . $path);

        if (!empty($this->settings->filename)) {
            $path2 = $TMP_DIR . "/" . $this->settings->filename;
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
