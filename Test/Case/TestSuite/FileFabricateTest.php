<?php
/** @noinspection PhpIncludeInspection */
require_once realpath(dirname(dirname(dirname(dirname(__FILE__))))."/vendor/autoload.php");
/** @noinspection PhpIncludeInspection */
require_once realpath(dirname(dirname(dirname(dirname(__FILE__))))."/TestSuite/FileFabricate.php");


class FileFabricateTest extends PHPUnit_Framework_TestCase {
    public function setUp() {
        parent::setUp();
    }

    public function tearDown() {
        parent::tearDown();
    }

    public function provider_csv_tsvファイルが出力できること() {
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
     * @dataProvider provider_csv_tsvファイルが出力できること
     * @param $data
     * @param $expectedCsv
     * @param $expectedTsv
     */
    public function test_tsv_csvファイルが出力できること($data, $expectedCsv, $expectedTsv) {
        $path = FileFabricate::createCsv($data);
        $this->assertEquals($expectedCsv, file_get_contents($path), "csv");

        $path = FileFabricate::createTsv($data);
        $this->assertEquals($expectedTsv, file_get_contents($path), "tsv");
    }

    public function test_BOMをつけて出力できること() {
        $data = [
            ["あ\"いう", "えお"],
            ["かきく", "けこ"],
        ];
        $path = FileFabricate::createCsv($data, ['bom' => true]);
        $this->assertEquals(
            bin2hex(FileFabricate::BOM_UTF8 . "\"あ\"\"いう\",えお\nかきく,けこ\n"),
            bin2hex(file_get_contents($path)),
            "csv");

        $path = FileFabricate::createTsv($data, ['bom' => true]);
        $this->assertEquals(
            bin2hex(FileFabricate::BOM_UTF8 . "\"あ\"\"いう\"\tえお\nかきく\tけこ\n"),
            bin2hex(file_get_contents($path)),
            "tsv");
    }

    public function provider_各種オプション() {
        return [
            "BOMが付けられる createFile" => [
                "createFile", ['bom' => true], "あいう",
                FileFabricate::BOM_UTF8 . "あいう",
            ],
            "BOMが付けられる createCsv" => [
                "createCsv", ['bom' => true], [["あ\"いう", "えお"], ["かきく", "けこ"]],
                FileFabricate::BOM_UTF8 . "\"あ\"\"いう\",えお\nかきく,けこ\n",
            ],
            "BOMが付けられる createTsv" => [
                "createTsv", ['bom' => true], [["あ\"いう", "えお"], ["かきく", "けこ"]],
                FileFabricate::BOM_UTF8 . "\"あ\"\"いう\"\tえお\nかきく\tけこ\n",
            ],
            "文字エンコーディングを指定できる createFile" => [
                "createFile", ['encoding' => 'SJIS'], "あいう",
                mb_convert_encoding("あいう", 'SJIS', 'UTF-8'),
            ],
            "文字エンコーディングを指定できる createCsv" => [
                "createCsv", ['encoding' => 'SJIS'], [["あ\"いう", "えお"], ["かきく", "けこ"]],
                mb_convert_encoding("\"あ\"\"いう\",えお\nかきく,けこ\n", 'SJIS', 'UTF-8'),
            ],
            "文字エンコーディングを指定できる createTsv" => [
                "createTsv", ['encoding' => 'SJIS'], [["あ\"いう", "えお"], ["かきく", "けこ"]],
                mb_convert_encoding("\"あ\"\"いう\"\tえお\nかきく\tけこ\n", 'SJIS', 'UTF-8'),
            ],
        ];
    }

    /**
     * @dataProvider provider_各種オプション
     * @param $method
     * @param $opt
     * @param $data
     * @param $expected
     */
    public function test_オプションを指定して呼び出せること($method, $opt, $data, $expected) {
        switch ($method) {
            case "createFile":
                $path = FileFabricate::createFile($data, $opt);
                break;
            case "createCsv":
                $path = FileFabricate::createCsv($data, $opt);
                break;
            case "createTsv":
                $path = FileFabricate::createTsv($data, $opt);
                break;
            default:
                throw new LogicException("Unknown:" . $method);
        }

        $this->assertEquals(
            bin2hex($expected),
            bin2hex(file_get_contents($path)));
    }

    public function provider_文字コード() {
        return [
            ['SJIS'],
        ];
    }

    /**
     * @dataProvider provider_文字コード
     * @param $encodeTo
     */
    public function test_文字コードを指定して出力できること($encodeTo) {
        $path = FileFabricate::createFile("あいう", ['encoding' => $encodeTo]);

        $this->assertEquals(
            bin2hex(mb_convert_encoding("あいう", $encodeTo, "UTF-8")),
            bin2hex(file_get_contents($path)),
            $encodeTo);
    }
}
 