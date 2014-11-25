<?php
/** @noinspection PhpIncludeInspection */
require_once realpath(dirname(dirname(__FILE__)) . "/vendor/autoload.php");
/** @noinspection PhpIncludeInspection */
require_once realpath(dirname(dirname(__FILE__)) . "/src/FileFabricate.php");


class FileFabricateTest extends PHPUnit_Framework_TestCase {
    public function setUp() {
        parent::setUp();
        FileFabricate::$dir = dirname(__FILE__) . "/tmp";
    }

    public function tearDown() {
        parent::tearDown();
    }

    public function test_２次元配列から作成() {
        $path = FileFabricate::from2DimensionalArray([
            ["あい", "うえ"],
            ["かき", "くけ"],
        ])->toCsv()->getPath();
        $this->assertEquals("あい,うえ\nかき,くけ\n", file_get_contents($path));
    }

    public function test_２次元配列から作成した場合の末尾の改行コードをなしにできる() {
        $path = FileFabricate::from2DimensionalArray([
            ["あい", "うえ"],
            ["かき", "くけ"],
        ])->withoutBrAtEof()->toCsv()->getPath();
        $this->assertEquals("あい,うえ\nかき,くけ", file_get_contents($path));
    }

    public function test_文字から作成() {
        $path = FileFabricate::fromString("あいうえお")->getPath();
        $this->assertEquals("あいうえお", file_get_contents($path));
    }

    public function test_CSVでなくTSVで出力() {
        $path = FileFabricate::from2DimensionalArray([
            ["あい", "うえ"],
            ["かき", "くけ"],
        ])->toTsv()->getPath();
        $this->assertEquals("あい\tうえ\nかき\tくけ\n", file_get_contents($path));
    }

    public function test_文字エンコーディングを指定_CSV() {
        $path = FileFabricate::from2DimensionalArray([
            ["あい", "うえ"],
            ["かき", "くけ"],
        ])->toCsv()->encodeTo("SJIS")->getPath();
        $this->assertEquals(
            bin2hex(mb_convert_encoding("あい,うえ\nかき,くけ\n", "SJIS", "UTF-8")),
            bin2hex(file_get_contents($path))
        );
    }

    public function test_文字エンコーディングを指定_文字列() {
        $path = FileFabricate::fromString("あいうえお")->encodeTo("SJIS")->getPath();
        $this->assertEquals(
            bin2hex(mb_convert_encoding("あいうえお", "SJIS", "UTF-8")),
            bin2hex(file_get_contents($path))
        );
    }

    public function test_BOMをつけて出力できること_CSV() {
        $path = FileFabricate::from2DimensionalArray([
            ["あ\"いう", "えお"],
            ["かきく", "けこ"],
        ])->toCsv()->prependUtf8Bom()->getPath();
        $this->assertEquals(
            bin2hex("\xef\xbb\xbf" . '"あ""いう",えお' . "\n" . 'かきく,けこ' . "\n"),
            bin2hex(file_get_contents($path))
        );
    }

    public function test_BOMをつけて出力できること_文字列() {
        $path = FileFabricate::fromString("あいうえお")->prependUtf8Bom()->getPath();
        $this->assertEquals(
            bin2hex("\xef\xbb\xbfあいうえお"),
            bin2hex(file_get_contents($path))
        );
    }

    public function test_テンポラリファイルの保存場所を全体的に変更したい場合() {
        $TMP_DIR = dirname(dirname(dirname(__FILE__))) . "/tmp_in_tmp";
        FileFabricate::$dir = $TMP_DIR;
        $path = FileFabricate::fromString("あいうえお")->getPath();
        $this->stringStartsWith($TMP_DIR . "/", $path);
        $this->assertEquals("あいうえお", file_get_contents($path));
    }

    public function test_テンポラリファイルの保存場所を個別に変更できる() {
        $TMP_DIR = dirname(dirname(dirname(__FILE__))) . "/tmp_in_tmp";
        $path = FileFabricate::fromString("あいうえお")->moveDirectoryTo($TMP_DIR)->getPath();
        $this->stringStartsWith($TMP_DIR . "/", $path);
        $this->assertEquals("あいうえお", file_get_contents($path));
    }

    public function test_テンポラリファイルのファイル名を変更できる() {
        $path = FileFabricate::fromString("あいうえお")->changeFileNameTo("FileFabricateTest.1234.txt")->getPath();
        $this->stringEndsWith("/FileFabricateTest.1234.txt", $path);
        $this->assertEquals("あいうえお", file_get_contents($path));
    }

    /**
     * @expectedException LogicException
     * @expectedExceptionMessage Already exists:
     * @expectedExceptionMessage /tmp/FileFabricateTest.1234.txt
     */
    public function test_テンポラリファイルのファイル名を既存のファイルと重複する名前に変更したら例外が発生する() {
        FileFabricate::fromString("これは既存ファイル")->changeFileNameTo("FileFabricateTest.1234.txt")->getPath();
        FileFabricate::fromString("あいうえお")->changeFileNameTo("FileFabricateTest.1234.txt")->getPath();
    }

    public function provider_csv_tsvファイルの出力仕様() {
        return [
            "ファイルが出力できること"             => [
                [["あいう", "えお"], ["かきく", "けこ"]],
                "あいう,えお\nかきく,けこ\n",
                "あいう\tえお\nかきく\tけこ\n",
            ],
            "ダブルクォートはエスケープされること"       => [
                [['あ"い']],
                '"あ""い"' . "\n",
                '"あ""い"' . "\n",
            ],
            "改行はエスケープされること"            => [
                [["あ\nい"]],
                "\"あ\nい\"\n",
                "\"あ\nい\"\n",
            ],
            "エスケープが不要ならセルはエスケープされないこと" => [
                [["あ\nい", "うえ"]],
                "\"あ\nい\",うえ\n",
                "\"あ\nい\"\tうえ\n",
            ],
        ];
    }

    /**
     * @dataProvider provider_csv_tsvファイルの出力仕様
     * @param $data
     * @param $expectedCsv
     * @param $expectedTsv
     */
    public function test_tsv_csvファイルの出力仕様($data, $expectedCsv, $expectedTsv) {
        $path = FileFabricate::from2DimensionalArray($data)->toCsv()->getPath();
        $this->assertEquals($expectedCsv, file_get_contents($path), "csv");

        $path = FileFabricate::from2DimensionalArray($data)->toTsv()->getPath();
        $this->assertEquals($expectedTsv, file_get_contents($path), "tsv");
    }

    //例外をキャッチしてしまうと、ストリームが無事に閉じられてしまうため、ストリームが閉じられなかった場合のテストができない。
    //    /**
    //     * @expectedException PHPUnit_Framework_Error_Warning
    //     * @expectedExceptionMessage mb_convert_encoding(): Unknown encoding "Unknown9999"
    //     */
    //    public function test_未知の文字エンコーディングで例外が発生してもファイルは確実に削除されること() {
    //        FileFabricate::fromString("あいう")->encodeTo("Unknown9999")->getPath();
    //    }

    /**
     * @see http://php.net/manual/ja/mbstring.supported-encodings.php
     */
    public function provider_文字エンコーディング() {
        return [
            ['SJIS'],
            ['SJIS-win'],
            ['CP932'],
            ['UTF-16LE'],
            ['IsO-2022-jp'], //JIS
            ['GB2312'], //簡体字
            ['BIG5'], //繁体字
            //['ISO-8859-11'], //タイ語(サポートされていない)
            ['KOI8-R'], //ロシア語
            ['SJIS'],
        ];
    }

    /**
     * @dataProvider provider_文字エンコーディング
     * @param $encodeTo
     */
    public function test_文字エンコーディングを指定して出力できること($encodeTo) {
        $path = FileFabricate::fromString("あいう")->encodeTo($encodeTo)->getPath();

        $this->assertEquals(
            bin2hex(mb_convert_encoding("あいう", $encodeTo, "UTF-8")),
            bin2hex(file_get_contents($path)),
            $encodeTo);
    }

//    public function test_CSVの値を変更できること($encodeTo) {
//        $path = FileFabricate::from2DimensionalArray([
//            ["label 1", "label 2"],
//            ["あい", "うえ"],
//            ["かき", "くけ"],
//        ])->changeValue(["label 1" => "あい"], "label 2", "がぎ")->toCsv()->getPath();
//        $this->assertEquals("あい,うえ\nがぎ,くけ\n", file_get_contents($path));
//    }


    public function test_データをテンプレートから作成できる() {
        $template = FileFabricate::defineTemplate([
            'label 1' => FileFabricate::value_integer(4),
            'label 2' => FileFabricate::value_string(3)->format('%s@aaa.com'),
            'label 3' => FileFabricate::value_date('Y-m-d H'),
            'label 4' => FileFabricate::value_rotation(["T", "F"]),
            'label 5' => FileFabricate::value_callback(function ($i) {
                return "i:" . $i;
            }),
            'label 6' => ["t", "f"],
            'label 7' => range(1, 5),
            'label 8' => "zzz",
            'label 9' => 99,
        ]);
        $path = $template->rows(10)->toCsv()->getPath();
        $expected = '"label 1","label 2","label 3","label 4","label 5","label 6","label 7","label 8","label 9"' . "\n" .
            "1,AAA@aaa.com,\"2000-01-01 00\",T,i:0,t,1,zzz,99\n" .
            "2,BBB@aaa.com,\"2000-01-02 00\",F,i:1,f,2,zzz,99\n" .
            "3,CCC@aaa.com,\"2000-01-03 00\",T,i:2,t,3,zzz,99\n" .
            "4,DDD@aaa.com,\"2000-01-04 00\",F,i:3,f,4,zzz,99\n" .
            "1,EEE@aaa.com,\"2000-01-05 00\",T,i:4,t,5,zzz,99\n" .
            "2,FFF@aaa.com,\"2000-01-06 00\",F,i:5,f,1,zzz,99\n" .
            "3,GGG@aaa.com,\"2000-01-07 00\",T,i:6,t,2,zzz,99\n" .
            "4,HHH@aaa.com,\"2000-01-08 00\",F,i:7,f,3,zzz,99\n" .
            "1,III@aaa.com,\"2000-01-09 00\",T,i:8,t,4,zzz,99\n" .
            "2,JJJ@aaa.com,\"2000-01-10 00\",F,i:9,f,5,zzz,99\n";
        $this->assertEquals($expected, file_get_contents($path));
    }

    public function test_テンプレートで生成した値の一部を変更できる() {
        $template = FileFabricate::defineTemplate([
            'label 1' => FileFabricate::value_integer(4),
            'label 2' => FileFabricate::value_string(3),
        ]);
        $path = $template->rows(5)->changeValue(3, 'label 2', "ccc")->toCsv()->getPath();
        $expected = '"label 1","label 2"' . "\n" .
            "1,AAA\n" .
            "2,BBB\n" .
            "3,ccc\n" .
            "4,DDD\n" .
            "1,EEE\n";
        $this->assertEquals($expected, file_get_contents($path));
    }

    public function test_csvファイルから生成できる() {
        $path = FileFabricate::from2DimensionalArray([
            ["ラベル1", "ラベル2"],
            ["あい", "うえ"],
            ["かき", "くけ"],
        ])->toCsv()->getPath();
        $this->assertEquals("ラベル1,ラベル2\nあい,うえ\nかき,くけ\n", file_get_contents($path));

        $path = FileFabricate::fromCsv($path)->changeValue(2, "ラベル2", "グゲ")->toCsv()->getPath();
        $this->assertEquals("ラベル1,ラベル2\nあい,うえ\nかき,グゲ\n", file_get_contents($path));
    }

    public function test_テンプレートで生成した値の一部をCSVと指定した後でも変更できる() {
        $template = FileFabricate::defineTemplate([
            'label 1' => FileFabricate::value_integer(4),
            'label 2' => FileFabricate::value_string(3),
        ])->rows(5)->toCsv()->encodeTo("UTF-16LE");
        $path = $template->changeValue(3, 'label 2', "ccc")->getPath();
        $expected = '"label 1","label 2"' . "\n" .
            "1,AAA\n" .
            "2,BBB\n" .
            "3,ccc\n" .
            "4,DDD\n" .
            "1,EEE\n";
        $this->assertEquals($expected, mb_convert_encoding(file_get_contents($path), "UTF-8", "UTF-16LE"));
    }
}
 