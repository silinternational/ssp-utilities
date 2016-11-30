<?php
namespace Sil\SspUtilsTests;

include __DIR__ . '/../vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use Sil\SspUtils\Utils;
use Sil\SspUtils\DiscoUtils;
use Sil\SspUtils\Metadata;

class DiscoUtilsTest extends TestCase
{
    /*
     * The SP that does not have an IDPList entry so only get the Idp
     * that also doesn't have a list for SPS.
     */
    public function testGetReducedIdpList_SpBare()
    {
        $metadataPath = __DIR__ . '/fixtures/utils/metadata';
        $idpEntries = Metadata::getIdpMetadataEntries($metadataPath);

        $spEntityId = 'sp-bare';

        $expected = ['idp-bare' => $idpEntries['idp-bare']];

        $results = DiscoUtils::getReducedIdpList(
            $idpEntries,
            $metadataPath,
            $spEntityId
        );
        
        // echo PHP_EOL . "AAAAA" . PHP_EOL . var_export($results, true);
        
        $this->assertEquals($expected, $results);
    }

    /*
     * The SP that does have an IDPList entry so will get the Idps
     * that do not have Exclude by Default
     */
    public function testGetReducedIdpList_SpOnForSps()
    {
        $metadataPath = __DIR__ . '/fixtures/utils/metadata';
        $idpEntries = Metadata::getIdpMetadataEntries($metadataPath);

        $spEntityId = 'sp-onForSps';

        $expected = [
            'idp-bare' => $idpEntries['idp-bare'],
            'idp-forSps' => $idpEntries['idp-forSps']
        ];

        $results = DiscoUtils::getReducedIdpList(
            $idpEntries,
            $metadataPath,
            $spEntityId
        );

        // echo PHP_EOL . "AAAAA" . PHP_EOL . var_export($results, true);

        $this->assertEquals($expected, $results);
    }


}