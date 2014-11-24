<?php

/**
 * Class FileFabricate
 *
 * テスト用にファイルを簡単に出力するためのツール。
 * テスト後に(register_shutdown_functionで)自動的にファイルは削除される。
 */
class FileFabricate {
    public static $dir = null;

    public static function from2DimensionalArray($data) {
        return new FileFabricateDataCells($data);
    }

    public static function fromString($string) {
        return new FileFabricateFile($string);
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

    public function __construct($cells) {
        $this->cells = $cells;
    }

    public function toCsv($delimiter = ',', $enclosure = '"') {
        $cells = $this->cells;
        return new FileFabricateFile(function() use ($cells, $delimiter, $enclosure) {
            $str = "";
            foreach ($cells as $row) {
                $fh_memory = fopen("php://memory", "rw");
                fputcsv($fh_memory, $row, $delimiter, $enclosure);
                $size = ftell($fh_memory);
                fseek($fh_memory, 0);
                $str .= fread($fh_memory, $size);
                fclose($fh_memory);
            }
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
     * @return $this
     */
    public function withoutBrAtEof() {
        $this->withoutBrAtEof = true;
        return $this;
    }
}

/**
 * Class FileFabricateFile
 *
 * ファイル出力可能な文字列データ
 */
class FileFabricateFile {
    /** @var string|callable */
    private $data; //２次元配列

    private $encodeTo = 'UTF-8';
    private $bom = null;
    private $directory = null;
    private $filename = null;

    private $path = null; //作成済みのファイルパス

    /**
     * @param string|callable $data
     */
    public function __construct($data) {
        $this->data = $data;
    }

    private function makeFile() {
        //ファイルパス生成
        $path = $this->__tempnum();

        //$dataをファイルに出力
        $fh = fopen($path, 'w');
        try {

            //BOM
            if ($this->bom !== null) {
                fwrite($fh, $this->bom);
            }

            //データ取得
            $str = $this->data;
            if (is_callable($str)) {
                $str = $str();
            }

            //文字コード変換
            if ($this->encodeTo !== "UTF-8") {
                $str = mb_convert_encoding($str, $this->encodeTo, "UTF-8");
            }

            fwrite($fh, $str);

        } catch (Exception $e) {
            fclose($fh); //mb_convert_encoding 等でエラーになっても確実にストリームを閉じる
            throw $e;
        }
        fclose($fh);

        $this->path = $path;
    }

    public function getPath() {
        // 未作成なら作成する
        if (empty($this->path)) {
            $this->makeFile();
        }
        return $this->path;
    }

    public function encodeTo($encoding) {
        $this->encodeTo = $encoding;
        $this->path = null;
        return $this;
    }

    public function prependUtf8Bom() {
        $this->bom = "\xef\xbb\xbf";
        $this->path = null;
        return $this;
    }

    public function moveDirectoryTo($directory) {
        $this->directory = $directory;
        $this->path = null;
        return $this;
    }

    public function changeFileNameTo($filename) {
        $this->filename = $filename;
        $this->path = null;
        return $this;
    }

    private function __tempnum() {
        if (!empty($this->directory)) {
            $TMP_DIR = $this->directory;
        }
        elseif (!empty(FileFabricate::$dir)) {
            $TMP_DIR = FileFabricate::$dir;
        }
        else {
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