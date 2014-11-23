<?php

/**
 * Class FileFabricate
 *
 * テスト用にファイルを簡単に出力するためのツール。
 * テスト後に(register_shutdown_functionで)自動的にファイルは削除される。
 */
class FileFabricate {
    const BOM_UTF8 = "\xef\xbb\xbf";
    public static $TMP = '/tmp';

    public static function createCsv($data, $opt = []) {
        $path = self::__create($opt, function ($fh) use ($data, $opt) {
            $delimiter = self::defval($opt['delimiter'], ',');
            $enclosure = self::defval($opt['enclosure'], '"');
            foreach ($data as $row) {
                fputcsv($fh, $row, $delimiter, $enclosure);
            }
        });
        return $path;
    }

    public static function createTsv($data, $opt = []) {
        $opt['delimiter'] = "\t";
        return self::createCsv($data, $opt);
    }

    public static function createFile($str, $opt = []) {
        $path = self::__create($opt, function ($fh) use ($str) {
            fwrite($fh, $str);
        });
        return $path;
    }

    /**
     * @param array    $opt - ['bom' => trueならBOMあり(省略ならBOMなし), 'encoding' => エンコーディング(省略ならUTF-8)]
     * @param callable $callback
     * @return string  ファイルのパス
     * @see      文字エンコーディング: http://php.net/manual/ja/mbstring.supported-encodings.php
     */
    private static function __create($opt, $callback) {
        $encoding = self::defval($opt['encoding'], 'UTF-8');
        $bom = self::defval($opt['bom'], false);

        //ファイルパス生成
        $path = self::__tempnum();

        //$dataをファイルに出力
        $fh = fopen($path, 'w');

        //BOM
        if ($bom) {
            if ($encoding !== "UTF-8") {
                throw new LogicException("bomを指定できるのはencodingがUTF-8の場合のみです。");
            }
            fwrite($fh, FileFabricate::BOM_UTF8);
        }

        //データ出力
        $callback($fh);
        fclose($fh);

        //文字コード変換
        if ($encoding !== "UTF-8") {
            $str = file_get_contents($path);
            $path = self::__tempnum();
            $fh = fopen($path, "w");
            $str = mb_convert_encoding($str, $encoding, "UTF-8");
            fwrite($fh, $str);
            fclose($fh);
        }

        return $path;
    }

    private static function __tempnum() {
        $path = tempnam(self::$TMP, "tst");
        //CakeLog::write(LOG_DEBUG, "[tempfile create] " . $path);
        register_shutdown_function(function () use ($path) {
            if (file_exists($path)) {
                unlink($path);
                //CakeLog::write(LOG_DEBUG, "[tempfile remove] " . $path);
            }
        });
        return $path;
    }

    private static function defval(&$val, $def) {
        return (empty($val) ? $def : $val);
    }
}