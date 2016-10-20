<?php

namespace Sil\SspUtils;

include __DIR__ . '/../vendor/autoload.php';

use Sil\SspUtils\Metadata;

class AuthSourcesUtils
{

    const IDP_SOURCES_KEY = 'idpList'; // the current SP's array of acceptable IDP's
    
    const IDP_LOGO_KEY = 'logoURL'; // The IDP metadata array key for the url to the IDP's logo
    
    const EXCLUDE_KEY = 'excludeByDefault'; // Entry in an IDP's metadata
    
    const FOR_SPS_KEY = 'forSps';  // Entry in an IDP's metadata for SP exclusive whitelist
    

    /**
     *
     * Takes the original auth sources and reduces them down to the ones
     * the current SP is meant to see.
     *    The relevant entries in saml20-idp-remote.php would be ...
     *      - 'excludeByDefault' (boolean), which when set to True would keep this idp
     *        from being shown to SP's that don't explicitly include it in the
     *        'idpList' entry of their metadata.
     *      - 'forSps' (array), which when set would only allow this idp to be shown
     *        to SPs whose entity_id is included in this array.
     *
     * @param array $authSourcesConfig - The $config array from the authsources.php file
     * @param array $startSources
     *    Each sub array is expected to have a 'source' entry which would
     *    match an idp label found in the 
     *    authsources.php:$config['auth-choices']['sources'] array
     * @param string $spEntityId - The entity id of the current SP.
     * @param array $spMetadata - The current SP's metadata.
     * @param string $metadataPath - the path to the sqml20-idp-remote.php files.
     *
     * @return array of a subset of the original $startSources.
     */
    public static function getSources(
        $authSourcesConfig, 
        $startSources, 
        $spEntityId,
        $spMetadata, 
        $metadataPath
    ) {
        // Limit the Auth sources to the ones allowed for this SP
        $reducedSources = array();  // The final list of auth sources this SP can see  
        
        $idpMetadata = Metadata::getIdpMetadataEntries($metadataPath);
        $idpEntries = self::getIdpsFromAuthSources($authSourcesConfig);    
        
        $spSources = array();  // The list of IDP's this SP wants to know about

        if (array_key_exists(self::IDP_SOURCES_KEY, $spMetadata)) {        
            $spSources = $spMetadata[self::IDP_SOURCES_KEY];
        }
        
        foreach ($startSources as $source) {
            $idpLabel = $source['source'];
            $idpEntityId = $idpEntries[$idpLabel];
            
            // If there is no entry for the idp in authsources, skip it.
            if (isset($idpEntries[$idpLabel])) {
                $idpEntityId = $idpEntries[$idpLabel];
            } else {
                continue;
            }
            
            // If there is no entry for the idp in saml20-idp-remote.php, skip it.
            if (isset($idpMetadata[$idpEntityId])) {
                $idpMdEntry = $idpMetadata[$idpEntityId];
            } else {
                continue;
            }
            
            $forSpsList = Null;
            
            if (isset($idpMdEntry[self::FOR_SPS_KEY])) {
                $forSpsList = $idpMdEntry[self::FOR_SPS_KEY];
            }

            // If there is an exclusive white list for this IDP, but this SP
            // is not included, skip it
            if ($forSpsList !== Null && ! in_array($spEntityId, $forSpsList)) {
                continue;
            }         
        
            $excludeByDefault = False; 
            if (isset($idpMdEntry[self::EXCLUDE_KEY]) && 
                    $idpMdEntry[self::EXCLUDE_KEY] === True) {
                $excludeByDefault = True;
            }             
            
            // If the SP does not expect to know about certain IDP's and 
            // this idp does not want to be seen without an explicit request, skip it.
            if ( ! $spSources && $excludeByDefault === True) {
                continue;
            }  
          
            // If the SP only wants to know about certain IDP's and this one
            // is not one of those, skip it.
            if ($spSources) {
                if ( ! in_array($idpEntityId, $spSources)) {
                    continue;
                }
            }

            // Everything is OK, so add this idp as one of the auth sources
            $reducedSources[] = $source;
        }
        
        return $reducedSources;
    }
    

    public static function getIdpsFromAuthSources($authSourcesConfig) {
        $idpEntries = array();
        $idpLabels = $authSourcesConfig['auth-choices']['sources'];
        foreach ($idpLabels as $idpLabel) {
            if ( ! isset($authSourcesConfig[$idpLabel])) {           
                continue;
            }
            
            $idpConfig = $authSourcesConfig[$idpLabel];
            
            if (isset($idpConfig['idp'])) {
                $idpEntries[$idpLabel] = $idpConfig['idp'];
            }
        }
      
        return $idpEntries;
    }

    /**
     * @param string $path Path to directory containing authsources files
     * @param string $file (optional) Name of authsources file ... default is "authsources.php"
     */
    public static function getAuthSourcesConfig($path, $file="authsources.php")
    {  
        $pathFile = $path . '/' . $file;
        
        try {
            $authSourcesContents = include $pathFile;
            
            if ($config === Null or ! $config) {
                throw new InvalidAuthSourcesException(
                    'Invalid authsources config for ' . $pathFile . PHP_EOL . 
                        'Cannot be null or evaluate to false.',
                    1476966993
                );
            }
            
        } catch (\Exception $e) {
            throw $e;
        }
        
        return $config;
        
    }
    
    /**
     * Gets the logoURL entries from the IDP's metadata and adds them to 
     *   the sources array that is available to the multiauth page.
     * Doesn't add an entry at all, if it is missing or invalid.
     *
     * @param array &$sources 
     */
    public static function addIdpLogoUrls(
        &$sources, 
        $authSourcesConfig, 
        $metadataPath
    ) {     
        
        $idpMetadata = Metadata::getIdpMetadataEntries($metadataPath);
        $idpEntries = self::getIdpsFromAuthSources($authSourcesConfig);      

        foreach ($sources as &$source) {
            $idpLabel = $source['source'];
            $idpEntityId = $idpEntries[$idpLabel];    
            
            // If there is no entry for the idp in saml20-idp-remote.php, skip it
            if ( ! isset($idpMetadata[$idpEntityId])) {
                continue;
            }
            $idpMdEntry = $idpMetadata[$idpEntityId];
            
            // If there is no logo URL entry in the metadata, skip it
            if ( ! isset($idpMdEntry[self::IDP_LOGO_KEY])) {
                continue;
            }        

            $logoURL = $idpMdEntry[self::IDP_LOGO_KEY];          
            
            // sanitize and validate the url
            $logoURL = filter_var($logoURL, FILTER_SANITIZE_URL);
            if (!filter_var($logoURL, FILTER_VALIDATE_URL) === false) {
                $source[self::IDP_LOGO_KEY] = $logoURL; // valid url
            }   
        }  
    }
}