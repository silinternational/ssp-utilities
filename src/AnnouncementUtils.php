<?php

namespace Sil\SspUtils;

use Sil\SspUtils\AuthSourcesUtils;
use Sil\SspUtils\Utils;

include __DIR__ . '/../vendor/autoload.php';

class AnnouncementUtils
{
    const DATETIME_FORMAT = "Y-m-d H:i:s";
    
    const ANNOUNCEMENT_TEXT_KEY = 'announcement_text';
    
    const START_DATETIME_KEY = 'start_datetime';
    
    const END_DATETIME_KEY = 'end_datetime';
  
    /**
     * Gets info about an announcement from a php file.  
     * That file will normally be SSP_PATH/announcement/announcement.php
     * If the file does not return an array, this method will return the same value.
     *
     * If the file does return an array, this method will return the value
     *   of its 'announcement_text' entry, provided that ...
     *      it does not have a 'start_datetime' value (DateTime) in the future or
     *      an 'end_datetime' value (DateTime) in the past.
     *
     * @param string $sspPath (optional) - the path to the simplesamlphp folder  
     * @param string $folder (default: 'announcement')
     * @param string $file (default: 'announcement.php')
     *
     * @return string|Null
     */
    public static function getAnnouncement(
        $sspPath=Null, 
        $folder='announcement',
        $file='announcement.php'
    ) {  
        $sspPath = Utils::getSspPath($sspPath) ;
        
        try {
            $announcementInfo = include $sspPath . '/' . $folder . '/' . $file;
        } catch (Exception $e) {
            return Null;
        }
        
        if ( ! is_array($announcementInfo)) {
            return $announcementInfo;
        }
        
        $now = date(self::DATETIME_FORMAT);        

        try {
            // If a start time is set and it's in the future, return Null
            if (isset($announcementInfo[self::START_DATETIME_KEY])
                    && $announcementInfo[self::START_DATETIME_KEY] > $now) {
                return Null;
            }
        
            // If an end time is set and it's in the past, return Null
            if (isset($announcementInfo[self::END_DATETIME_KEY])
                    && $announcementInfo[self::END_DATETIME_KEY] < $now) {
                return Null;
            }
        } catch (Exception $e) {
            return Null;
        }
        
        // If there is announcement text, return that.  Otherwise, return Null
        if (isset($announcementInfo[self::ANNOUNCEMENT_TEXT_KEY])) {            
            return $announcementInfo[self::ANNOUNCEMENT_TEXT_KEY];
        }
        
        return Null;
    }
}