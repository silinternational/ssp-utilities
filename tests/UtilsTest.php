<?php
namespace Sil\SspUtilsTests;

include __DIR__ . '/../vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use Sil\SspUtils\Utils;
use Sil\SspUtils\Metadata;

class UtilsTest extends TestCase
{

    const IDP_SOURCES_KEY = 'IDPList';

    public function getIdpMdEntry($idpEntityId) {
        $metadataPath = __DIR__ . '/fixtures/utils/metadata';
        $idpEntries = Metadata::getIdpMetadataEntries($metadataPath);
        return $idpEntries[$idpEntityId];
    }

    public function getSpMdEntry($spEntityId) {
        $metadataPath = __DIR__ . '/fixtures/utils/metadata';
        $spEntries = Metadata::getSpMetadataEntries($metadataPath);
        return $spEntries[$spEntityId];
    }

    public function getIdpsForSp($spEntityId) {
        $spMdEntry = self::getSpMdEntry($spEntityId);

        $idps4Sp = array();  // The list of IDP's this SP wants to know about
        if (array_key_exists(self::IDP_SOURCES_KEY, $spMdEntry)) {
            $idps4Sp = $spMdEntry[self::IDP_SOURCES_KEY];
        }

        return $idps4Sp;
    }
    /*
     * The SP that does not have an IDPList entry and the IDP does not
     * have a forSps entry.  It should be valid.
     */
    public function testIsIdpValidForSp_SpBareIdpBare()
    {
        $idpEntityId = 'idp-bare';
        $idpMdEntry = self::getIdpMdEntry($idpEntityId);

        $spEntityId = 'sp-bare';
        $idps4Sp = self::getIdpsForSp($spEntityId);
        
        $results = Utils::isIdpValidForSp(
            $idpEntityId,
            $idpMdEntry,
            $spEntityId,
            $idps4Sp
        );
        
        // echo PHP_EOL . "AAAAA" . PHP_EOL . var_export($results, true);
        
        $this->assertTrue($results);
    }


    /*
     * The SP that does not have an IDPList entry but the IDP does
     * have Exclude by Default.  It should not be valid.
     */
    public function testIsIdpValidForSp_SpBareIdpExclude()
    {
        $idpEntityId = 'idp-exclude';
        $idpMdEntry = self::getIdpMdEntry($idpEntityId);

        $spEntityId = 'sp-bare';
        $idps4Sp = self::getIdpsForSp($spEntityId);

        $results = Utils::isIdpValidForSp(
            $idpEntityId,
            $idpMdEntry,
            $spEntityId,
            $idps4Sp
        );

        // echo PHP_EOL . "AAAAA" . PHP_EOL . var_export($results, true);

        $this->assertFalse($results);
    }

    /*
     * The SP that does not have an IDPList entry but the IDP does
     * have a White List that the SP is not on.  It should not be valid.
     */
    public function testIsIdpValidForSp_SpBareIdpHasList()
    {
        $idpEntityId = 'idp-forSps';
        $idpMdEntry = self::getIdpMdEntry($idpEntityId);

        $spEntityId = 'sp-bare';
        $idps4Sp = self::getIdpsForSp($spEntityId);

        $results = Utils::isIdpValidForSp(
            $idpEntityId,
            $idpMdEntry,
            $spEntityId,
            $idps4Sp
        );

        // echo PHP_EOL . "AAAAA" . PHP_EOL . var_export($results, true);

        $this->assertFalse($results);
    }

    /*
     * The SP that does not have an IDPList entry but the IDP does
     * have a White List that the SP is on.  It should be valid.
     */
    public function testIsIdpValidForSp_SpNoListButOnIdpList()
    {
        $idpEntityId = 'idp-forSps';
        $idpMdEntry = self::getIdpMdEntry($idpEntityId);

        $spEntityId = 'sp-onForSps';
        $idps4Sp = self::getIdpsForSp($spEntityId);

        $results = Utils::isIdpValidForSp(
            $idpEntityId,
            $idpMdEntry,
            $spEntityId,
            $idps4Sp
        );

        // echo PHP_EOL . "AAAAA" . PHP_EOL . var_export($results, true);

        $this->assertTrue($results);
    }

    /*
     * The SP that does not have an IDPList entry and the IDP does
     * have a White List that the SP is on but it also has
     * Exclude by Default.  It should be not valid.
     */
    public function testIsIdpValidForSp_SpNoListButOnIdpListButExclude()
    {
        $idpEntityId = 'idp-forSpsExclude';
        $idpMdEntry = self::getIdpMdEntry($idpEntityId);

        $spEntityId = 'sp-onForSps';
        $idps4Sp = self::getIdpsForSp($spEntityId);

        $results = Utils::isIdpValidForSp(
            $idpEntityId,
            $idpMdEntry,
            $spEntityId,
            $idps4Sp
        );

        // echo PHP_EOL . "AAAAA" . PHP_EOL . var_export($results, true);

        $this->assertFalse($results);
    }

    /*
     * The SP that does have an IDPList entry and the IDP does
     * have a White List that the SP is on and it does  have
     * Exclude by Default.  It should be valid.
     */
    public function testIsIdpValidForSp_SpWithListAndOnIdpListButExclude()
    {
        $idpEntityId = 'idp-forSpsExclude';
        $idpMdEntry = self::getIdpMdEntry($idpEntityId);

        $spEntityId = 'sp-onForSpsWithIdpList';
        $idps4Sp = self::getIdpsForSp($spEntityId);

        $results = Utils::isIdpValidForSp(
            $idpEntityId,
            $idpMdEntry,
            $spEntityId,
            $idps4Sp
        );

        // echo PHP_EOL . "AAAAA" . PHP_EOL . var_export($results, true);

        $this->assertTrue($results);
    }

}