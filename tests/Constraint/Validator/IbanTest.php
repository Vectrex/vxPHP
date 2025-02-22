<?php
/**
 * Created by PhpStorm.
 * User: gregor
 * Date: 28.11.17
 * Time: 21:36
 */

namespace Constraint\Validator;

use PHPUnit\Framework\Attributes\DataProvider;
use vxPHP\Constraint\Validator\Iban;
use PHPUnit\Framework\TestCase;

class IbanTest extends TestCase
{

    public static function getValidIbans(): array
    {
        return [
            ['BE68844010370034'],
            ['MC1112739000700011111000H79'],
            ['FI2112345600000785'],
            ['LV80BANK0000435195001'],
            ['DE27100777770209299700'],
            ['DE11520513735120710131'],
            ['AT411100000237571500'],
            ['BE68844010370034'],
            ['EE34 2200 2210 3412 6658'],
            ['AL47 2121 1009 0000 0002 3569 8741'],
            ['AD12 0001 2030 2003 5910 0100'],
            ['AT61 1904 3002 3457 3201'],
            ['AZ21 NABZ 0000 0000 1370 1000 1944'],
            ['BH67 BMAG 0000 1299 1234 56'],
            ['BE62 5100 0754 7061'],
            ['BA39 1290 0794 0102 8494'],
            ['BG80 BNBG 9661 1020 3456 78'],
            ['HR12 1001 0051 8630 0016 0'],
            ['CY17 0020 0128 0000 0012 0052 7600'],
            ['CZ65 0800 0000 1920 0014 5399'],
            ['DK50 0040 0440 1162 43'],
            ['EE38 2200 2210 2014 5685'],
            ['FO97 5432 0388 8999 44'],
            ['FI21 1234 5600 0007 85'],
            ['FR14 2004 1010 0505 0001 3M02 606'],
            ['GE29 NB00 0000 0101 9049 17'],
            ['DE89 3704 0044 0532 0130 00'],
            ['GI75 NWBK 0000 0000 7099 453'],
            ['GR16 0110 1250 0000 0001 2300 695'],
            ['GL56 0444 9876 5432 10'],
            ['HU42 1177 3016 1111 1018 0000 0000'],
            ['IS14 0159 2600 7654 5510 7303 39'],
            ['IE29 AIBK 9311 5212 3456 78'],
            ['IL62 0108 0000 0009 9999 999'],
            ['IT40 S054 2811 1010 0000 0123 456'],
            ['LV80 BANK 0000 4351 9500 1'],
            ['LB62 0999 0000 0001 0019 0122 9114'],
            ['LI21 0881 0000 2324 013A A'],
            ['LT12 1000 0111 0100 1000'],
            ['LU28 0019 4006 4475 0000'],
            ['MK072 5012 0000 0589 84'],
            ['MT84 MALT 0110 0001 2345 MTLC AST0 01S'],
            ['MU17 BOMM 0101 1010 3030 0200 000M UR'],
            ['MD24 AG00 0225 1000 1310 4168'],
            ['MC93 2005 2222 1001 1223 3M44 555'],
            ['ME25 5050 0001 2345 6789 51'],
            ['NL39 RABO 0300 0652 64'],
            ['NO93 8601 1117 947'],
            ['PK36 SCBL 0000 0011 2345 6702'],
            ['PL60 1020 1026 0000 0422 7020 1111'],
            ['PT50 0002 0123 1234 5678 9015 4'],
            ['RO49 AAAA 1B31 0075 9384 0000'],
            ['SM86 U032 2509 8000 0000 0270 100'],
            ['SA03 8000 0000 6080 1016 7519'],
            ['RS35 2600 0560 1001 6113 79'],
            ['SK31 1200 0000 1987 4263 7541'],
            ['SI56 1910 0000 0123 438'],
            ['ES80 2310 0001 1800 0001 2345'],
            ['SE35 5000 0000 0549 1000 0003'],
            ['CH93 0076 2011 6238 5295 7'],
            ['TN59 1000 6035 1835 9847 8831'],
            ['TR33 0006 1005 1978 6457 8413 26'],
            ['AE07 0331 2345 6789 0123 456'],
            ['GB12 CPBK 0892 9965 0449 91'],
            /*
            ['AO06000600000100037131174'],
            ['AZ21NABZ00000000137010001944'],
            ['BH29BMAG1299123456BH00'],
            ['BJ11B00610100400271101192591'],
            ['BR9700360305000010009795493P1'],
            ['BR1800000000141455123924100C2'],
            ['VG96VPVG0000012345678901'],
            ['BF1030134020015400945000643'],
            ['BI43201011067444'],
            ['CM2110003001000500000605306'],
            ['CV64000300004547069110176'],
            ['FR7630007000110009970004942'],
            ['CG5230011000202151234567890'],
            ['CR0515202001026284066'],
            ['DO28BAGR00000001212453611324'],
            ['GT82TRAJ01020000001210029690'],
            ['IR580540105180021273113007'],
            ['IL620108000000099999999'],
            ['CI05A00060174100178530011852'],
            ['JO94CBJO0010000000000131000302'],
            ['KZ176010251000042993'],
            ['KW74NBOK0000000000001000372151'],
            ['LB30099900000001001925579115'],
            ['MG4600005030010101914016056'],
            ['ML03D00890170001002120000447'],
            ['MR1300012000010000002037372'],
            ['MU17BOMM0101101030300200000MUR'],
            ['MZ59000100000011834194157'],
            ['PS92PALS000000000400123456702'],
            ['QA58DOHB00001234567890ABCDEFG'],
            ['XK051212012345678906'],
            ['PT50000200000163099310355'],
            ['SA0380000000608010167519'],
            ['SN12K00100152000025690007542'],
            ['TL380080012345678910157'],
            ['TN5914207207100707129648'],
            ['TR330006100519786457841326'],
            ['AE260211000000230064016'],
            */
        ];
    }

    public static function getInvalidIbans(): array
    {
        return [
            ['AT123'],
            ['AT1234567890123456789012345678901234567890'],
            ['AD1200012030200359100120'],
            ['AT611904300234573221'],
            ['BA39129007940028494'],
            ['F12112345600000785'],
            ['BE685390047034'],
            ['AA611904300234573201'],
            ['AT795700000210284113'],
            ['AL47 2121 1009 0000 0002 3569 874'], //Albania
            ['AD12 0001 2030 2003 5910 010'], //Andorra
            ['AT61 1904 3002 3457 320'], //Austria
            ['AZ21 NABZ 0000 0000 1370 1000 194'], //Azerbaijan
            ['AZ21 N1BZ 0000 0000 1370 1000 1944'], //Azerbaijan
            ['BH67 BMAG 0000 1299 1234 5'], //Bahrain
            ['BH67 B2AG 0000 1299 1234 56'], //Bahrain
            ['BE62 5100 0754 7061 2'], //Belgium
            ['BA39 1290 0794 0102 8494 4'], //Bosnia and Herzegovina
            ['BG80 BNBG 9661 1020 3456 7'], //Bulgaria
            ['BG80 B2BG 9661 1020 3456 78'], //Bulgaria
            ['HR12 1001 0051 8630 0016 01'], //Croatia
            ['CY17 0020 0128 0000 0012 0052 7600 1'], //Cyprus
            ['CZ65 0800 0000 1920 0014 5399 1'], //Czech Republic
            ['DK50 0040 0440 1162 431'], //Denmark
            ['EE38 2200 2210 2014 5685 1'], //Estonia
            ['FO97 5432 0388 8999 441'], //Faroe Islands
            ['FI21 1234 5600 0007 851'], //Finland
            ['FR14 2004 1010 0505 0001 3M02 6061'], //France
            ['GE29 NB00 0000 0101 9049 171'], //Georgia
            ['DE89 3704 0044 0532 0130 001'], //Germany
            ['GI75 NWBK 0000 0000 7099 4531'], //Gibraltar
            ['GR16 0110 1250 0000 0001 2300 6951'], //Greece
            ['GL56 0444 9876 5432 101'], //Greenland
            ['HU42 1177 3016 1111 1018 0000 0000 1'], //Hungary
            ['IS14 0159 2600 7654 5510 7303 391'], //Iceland
            ['IE29 AIBK 9311 5212 3456 781'], //Ireland
            ['IL62 0108 0000 0009 9999 9991'], //Israel
            ['IT40 S054 2811 1010 0000 0123 4561'], //Italy
            ['LV80 BANK 0000 4351 9500 11'], //Latvia
            ['LB62 0999 0000 0001 0019 0122 9114 1'], //Lebanon
            ['LI21 0881 0000 2324 013A A1'], //Liechtenstein
            ['LT12 1000 0111 0100 1000 1'], //Lithuania
            ['LU28 0019 4006 4475 0000 1'], //Luxembourg
            ['MK072 5012 0000 0589 84 1'], //Macedonia
            ['MT84 MALT 0110 0001 2345 MTLC AST0 01SA'], //Malta
            ['MU17 BOMM 0101 1010 3030 0200 000M URA'], //Mauritius
            ['MD24 AG00 0225 1000 1310 4168 1'], //Moldova
            ['MC93 2005 2222 1001 1223 3M44 5551'], //Monaco
            ['ME25 5050 0001 2345 6789 511'], //Montenegro
            ['NL39 RABO 0300 0652 641'], //Netherlands
            ['NO93 8601 1117 9471'], //Norway
            ['PK36 SCBL 0000 0011 2345 6702 1'], //Pakistan
            ['PL60 1020 1026 0000 0422 7020 1111 1'], //Poland
            ['PT50 0002 0123 1234 5678 9015 41'], //Portugal
            ['RO49 AAAA 1B31 0075 9384 0000 1'], //Romania
            ['SM86 U032 2509 8000 0000 0270 1001'], //San Marino
            ['SA03 8000 0000 6080 1016 7519 1'], //Saudi Arabia
            ['RS35 2600 0560 1001 6113 791'], //Serbia
            ['SK31 1200 0000 1987 4263 7541 1'], //Slovak Republic
            ['SI56 1910 0000 0123 4381'], //Slovenia
            ['ES80 2310 0001 1800 0001 2345 1'], //Spain
            ['SE35 5000 0000 0549 1000 0003 1'], //Sweden
            ['CH93 0076 2011 6238 5295 71'], //Switzerland
            ['TN59 1000 6035 1835 9847 8831 1'], //Tunisia
            ['TR33 0006 1005 1978 6457 8413 261'], //Turkey
            ['AE07 0331 2345 6789 0123 4561'], //UAE
            ['GB12 CPBK 0892 9965 0449 911'], //United Kingdom

        ];
    }

    #[DataProvider('getInvalidIbans')]
    public function testInvalidIban($value): void
    {

        $this->assertFalse((new Iban())->validate($value), sprintf("Invalid '%s' did validate.", $value));

    }

    #[DataProvider('getValidIbans')]
    public function testValidIban($value): void
    {

        $this->assertTrue((new Iban())->validate($value), sprintf("Valid '%s' did not validate.", $value));

    }

}
