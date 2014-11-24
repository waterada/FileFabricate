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
}
 