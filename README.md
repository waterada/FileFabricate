FileFabricate
=============

[![Build Status](https://travis-ci.org/waterada/FileFabricate.svg?branch=master)](https://travis-ci.org/waterada/FileFabricate)


概要(summary)
-------------

単体テストでファイルを動的に生成するためのもの。
ファイルは処理終了後に自動的に削除される。

This is used to fabricate a test file.
The test file which you fabricate is automatically removed.


使用例(example):
----------------

```php
// ２次元配列から作成 (Making from a two-dimensional array.)
$path = FileFabricate::from2DimensionalArray([
    ["あい", "うえ"],
    ["かき", "くけ"],
])->toCsv()->getPath();


// 文字から作成 (Making from a string.)
$path = FileFabricate::fromString("あいうえお")->getPath();


// 外部CSVファイルから作成 (Making from a csv file.)
$path = FileFabricate::fromCsv("/path")->getPath();


// CSVでなくTSVで出力 (Outputing as tsv instead of csv.)
$path = FileFabricate::from2DimensionalArray([
    ["あい", "うえ"],
    ["かき", "くけ"],
])->toTsv()->getPath();


// 文字エンコーディングを指定 (Specifying encoding.)
// mb_convert_encoding で変換。 (encoded with mb_convert_encoding.)
$path = FileFabricate::from2DimensionalArray([
    ["あい", "うえ"],
    ["かき", "くけ"],
])->toCsv()->encodeTo("SJIS")->getPath();

$path = FileFabricate::fromString("あいうえお")->encodeTo("SJIS")->getPath();


// UTF-8でBOM付きにする場合 (Prepending the BOM of UTF-8.)
$path = FileFabricate::fromString("あいうえお")->prependUtf8Bom()->getPath();


// ファイル名を指定 (Specifying the file name.)
$path = FileFabricate::fromString("あいうえお")->changeFileNameTo("aaa.csv")->getPath();


// テンポラリファイルの保存場所を全体的に変更したい場合 (Changing the base directory to save files.)
// デフォルトは /tmp (The default is '/tmp'.)
FileFabricate::$dir = "/var/tmp";
$path = FileFabricate::fromString("あいうえお")->getPath();


// テンポラリファイルの保存場所を今回だけ変更したい場合 (Changing the directory to save the file in this time.)
$path = FileFabricate::fromString("あいうえお")->moveDirectoryTo("/var/tmp")->getPath();


// テンプレートを定義して動的に作成することも可能 (You may make the template to fabricate data.)
$template = FileFabricate::defineTemplate([
    'label 1' => FileFabricate::value_integer(15),
    'label 2' => FileFabricate::value_string(15)->format('%s@aaa.com'),
    'label 3' => FileFabricate::value_date('Y-m-d'),
    'label 4' => FileFabricate::value_rotation(["T", "F"]),
    'label 5' => FileFabricate::value_callback(function($i) { return $i; }),
    'label 6' => ["T", "F"],
    'label 7' => range(1, 12),
    'label 8' => "aaa",
    'label 9' => 1,
]);
$path = $template->rows(15)->toCsv()->getPath();


// テンプレートで生成した値の一部の変更も可能 (You may change the value which you fabricate.)
$template = FileFabricate::defineTemplate([
    'label 1' => FileFabricate::value_integer(4),
    'label 2' => FileFabricate::value_string(3),
]);
$path = $template->rows(5)->change_value(3, 'label 2', "ccc")->toCsv()->getPath();


// 文字コードを指定した後に生成した値を変更することも可能 (You may change the value after specifying encoding.)
$template = FileFabricate::defineTemplate([
    'label 1' => FileFabricate::value_integer(4),
    'label 2' => FileFabricate::value_string(3),
])->rows(5)->toCsv()->encodeTo("UTF-16LE");
$path = $template->change_value(3, 'label 2', "ccc")->getPath();
```
