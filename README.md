FileFabricate
=============

[![Build Status](https://travis-ci.org/waterada/FileFabricate.svg?branch=master)](https://travis-ci.org/waterada/FileFabricate)

単体テストでファイルを動的に生成するためのもの
ファイルは処理終了後に自動的に削除される。

使用例:

```php
// ２次元配列から作成 (In case of making from a two-dimensional array.)
$path = FileFabricate::from2DimensionalArray([
    ["あい", "うえ"],
    ["かき", "くけ"],
])->toCsv()->getPath();


// 文字から作成 (In case of making from a string.)
$path = FileFabricate::fromString("あいうえお")->getPath();


// CSVでなくTSVで出力 (In case of outputing as tsv instead of csv.)
$path = FileFabricate::from2DimensionalArray([
    ["あい", "うえ"],
    ["かき", "くけ"],
])->toTsv()->getPath();


// 文字エンコーディングを指定 (In case of specifying encoding.)
// mb_convert_encoding で変換。 (encoded with mb_convert_encoding.)
$path = FileFabricate::from2DimensionalArray([
    ["あい", "うえ"],
    ["かき", "くけ"],
])->toCsv()->encodeTo("SJIS")->getPath();

$path = FileFabricate::fromString("あいうえお")->encodeTo("SJIS")->getPath();


// UTF-8でBOM付きにする場合 (In case of prepending the BOM of UTF-8.)
$path = FileFabricate::fromString("あいうえお")->prependUtf8Bom()->getPath();


// ファイル名を指定 (In case of specifying the file name.)
$path = FileFabricate::fromString("あいうえお")->changeFileNameTo("aaa.csv")->getPath();


// テンポラリファイルの保存場所を全体的に変更したい場合 (In case of changing the base directory to save the file.)
// デフォルトは /tmp (The default is '/tmp'.)
FileFabricate::$dir = "/var/tmp";
$path = FileFabricate::fromString("あいうえお")->getPath();


// テンポラリファイルの保存場所を今回だけ変更したい場合 (In case of changing the directory to save the file in this time.)
$path = FileFabricate::fromString("あいうえお")->moveDirectoryTo("/var/tmp")->getPath();

// テンプレートを定義して動的に作成することも可能 (You may make the template to fabricate data.)
$template = FileFabricate::defineTemplate();
$template->definition = [
    'label 1' => $template->value_integer(15),
    'label 2' => $template->value_string(15)->format('%s@aaa.com'),
    'label 3' => $template->value_date('Y-m-d'),
    'label 4' => $template->value_rotation(["T", "F"]),
    'label 5' => $template->value_callback(function($i) { return $i; }),
    'label 6' => ["T", "F"],
    'label 7' => range(1, 12),
    'label 8' => "aaa",
    'label 9' => 1,
];
$path = $template->rows(15)->change_value(3, 'label 8', "bbb")->toCsv()->getPath();

// テンプレートで生成した値の一部の変更も可能 (You may change the value which you fabricate.)
$template = FileFabricate::defineTemplate();
$template->definition = [
    'label 1' => $template->value_integer(4),
    'label 2' => $template->value_string(3),
];
$path = $template->rows(5)->change_value(3, 'label 2', "ccc")->toCsv()->getPath();
```