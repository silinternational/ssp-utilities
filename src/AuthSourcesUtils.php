<?php

namespace Sil\SspUtils;

use Sil\SspUtils\Metadata;

class AuthSourcesUtils
{

    /**
     * Takes the original auth sources and reduces them down to the ones
     * the current SP is meant to see. Wrapper for getSources (below), but
     * does not require an authState parameter like the getSourcesForSp method.
     *
     * @param array $authSourcesConfig - must have a 'auth-choices' element
     *    which is an array that has a 'sources' element that is an array of strings
     * @param string $spEntityId
     * @param array $spMetadata - the current SP's metadata
     * @param string $sspPath (optional), the path to the simplesamlphp folder
     *
     * @returns array of strings of entity id's of IDP's that this SP
     *     is allowed to use for authentication.     
     */
    public static function getIdpsForSpNoAuthState(
        $authSourcesConfig,
        $spEntityId,
        $spMetadata,
        $sspPath=Null
    ) {

        $sspPath = Utils::getSspPath($sspPath);
        $mdPath = $sspPath . '/metadata';

        $startSources = [];
        $authChoices = $authSourcesConfig['auth-choices']['sources'];

        foreach ($authChoices as $nextChoice) {
            $startSources[] = ['source' => $nextChoice];
        }

        $reducedSources = self::getSources(
            $authSourcesConfig,
            $startSources,
            $spEntityId,
            $spMetadata,
            $mdPath
        );

       $allowedSources = [];


       foreach ($reducedSources as $nextSource) {
           $allowedSources[] = $authSourcesConfig[$nextSource['source']]['idp'];
       }

        return $allowedSources;
    }    
        
        
    /**
     * Wrapper for getSources() and also addIdpLogoUrls() (see below)
     *
     * @param array $startSources, the authsources array that ssp provides to a theme
     * @param string $authState, the AuthState string that ssp provides to a 
     *    theme via $_GET['AuthState']
     * @param string $sspPath (optional), the path to the simplesamlphp folder
     *
     * @return array of a subset of the original $startSources.     
     **/        
    public static function getSourcesWithLogoUrls(
        $startSources, 
        $authState, 
        $sspPath=Null
    ) {
        $sspPath = Utils::getSspPath($sspPath);
        $asPath = $sspPath . '/config';
        $mdPath = $sspPath . '/metadata';

        $authSourcesConfig = self::getAuthSourcesConfig($asPath);

        $reducedSources = self::getSourcesForSp($startSources, $authState, $sspPath);
        
        self::addIdpLogoUrls(
            $reducedSources,
            $authSourcesConfig,
            $mdPath
        );    

        return $reducedSources;        
    }
    
    /**
     * Wrapper for getSources()  (see below)
     *
     * @param array $startSources, the authsources array that ssp provides to a theme
     * @param string $authState, the AuthState string that ssp provides to a 
     *    theme via $_GET['AuthState']
     * @param string $sspPath (optional), the path to the simplesamlphp folder. 
     *     If Null, tries to get it from the SSP_PATH environment variable.
     *     
     * @return array of a subset of the original $startSources.
     **/
    public static function getSourcesForSp(
        $startSources, 
        $authState, 
        $sspPath=Null
    ) {
      
        $sspPath = Utils::getSspPath($sspPath);
        $mdPath = $sspPath . '/metadata';
        $asPath = $sspPath . '/config';
        $authSourcesConfig = self::getAuthSourcesConfig($asPath);
        

        $spEntries = Metadata::getSpMetadataEntries($mdPath);        
        $spEntityId = AuthStateUtils::getSpEntityIdForMultiAuth($authState);
        $spMetadata = $spEntries[$spEntityId];

        $reducedSources = self::getSources(
            $authSourcesConfig,
            $startSources,
            $spEntityId,
            $spMetadata,
            $mdPath
        );    

        return $reducedSources;
    }    

    
    /**
     *
     * Takes the original auth sources and reduces them down to the ones
     * the current SP is meant to see.
     *    The relevant entries in saml20-idp-remote.php would be ...
     *      - 'excludeByDefault' (boolean), which when set to True would keep this idp
     *        from being shown to SP's that don't explicitly include it in the
     *        'IDPList' entry of their metadata.
     *      - 'SPList' (array), which when set would only allow this idp to be shown
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
        if (array_key_exists(Utils::IDP_LIST_KEY, $spMetadata)) {
            $spSources = $spMetadata[Utils::IDP_LIST_KEY];
        }

        foreach ($startSources as $source) {
            $idpLabel = $source['source'];

            if (! isset($idpEntries[$idpLabel])) { 
              continue; 
            }
            
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

            if ( ! Utils::isIdpValidForSp($idpEntityId,
                                          $idpMdEntry,
                                          $spEntityId,
                                          $spSources)) {
                continue;
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
            if ( ! isset($idpMdEntry[Utils::IDP_LOGO_KEY])) {
                continue;
            }        

            $logoURL = $idpMdEntry[Utils::IDP_LOGO_KEY];
            
            // sanitize the url (remove bad characters and just return false if it's not a string)
            $logoURL = filter_var($logoURL, FILTER_SANITIZE_URL);
            $source[Utils::IDP_LOGO_KEY] = $logoURL;
        }  
    }
}