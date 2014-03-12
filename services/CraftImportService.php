<?php

namespace Craft;

class CraftImportService extends BaseApplicationComponent
{

    protected $config;
    protected $importTags;
    protected $tagsData;
    protected $importCategories;
    protected $categoriesData;
    protected $entryId;

    public function __construct()
    {
        /**
         * Tags
         * @var boolean
         */
        $this->importTags = true;

        /**
         * Categories
         * @var boolean
         */
        $this->importCategories = true;
        $this->categoriesData = array(
            'categories' => array(
                'fieldId' => 30,
            ),
        );
    }

    /**
     * Try to create entries
     * @return (mixed)
     */
    public function loadEntries()
    {
        /**
         * Let's not bomb out!
         */
        set_time_limit(0);

        $retVal = false;

        // Use SimpleXML to fetch an XML export of channel data from an ExpressionEngine site
        $xml = simplexml_load_file('http://synergema.com/blog-export');

        /**
         * Should we try to import Tags?
         * @var boolean
         */
        $importTags = true;

        /**
         * An array of tags to be imported.
         * @var array 
         *          name    = The corresponding element in the XML field.
         *          setId   = Matching Tags set ID in Craft.
         *          fieldId = Matching Tags field ID in Craft.
         */
        $tagsData = array(
            // 'categories' => array(
            //     'setId'   => 2,
            //     'fieldId' => 30,
            // ),
            'tags' => array(
                'setId'   => 3,
                'fieldId' => 3,
            ),
        );

        /**
         * Set the section and section type for importing of entries.
         * @var integer
         */
        $sectionId = 8; // Visit settings for your Section and check the URL
        $typeId = 8; // Visit Entry Types for your Section and check the URL for the Entry Type

        /**
         * Loop through <entry> tags.
         */
        foreach ($xml->blog[0]->entry as $importEntry)
        {

            // Validate fetch on screen
            if( $retVal )
            {
                // echo $importEntry->entry_date . '<br />';
                // echo $importEntry->title . '<br />';
                // echo $importEntry->slug . '<br />';
                // echo $importEntry->post . '<br />';
                // echo '<br />';
            }

            // Swap Assets URLs in posts
            // http://s3.amazonaws.com/YOURBUCKET/uploads
            //$newUrl = addslashes('http://s3.amazonaws.com/YOURBUCKET/uploads'); // Actually...Should not be needed
            $newUrl = 'http://cdn.synergema.com/uploads/blog/legacy';
            
            // Make sure you reference this variable below!
            $post = StringHelper::arrayToString( $importEntry->post );

            // Make sure you run the string containing a subsequent substring first!
            $post = str_replace('http://synergema.com/_media/uploads', $newUrl, $post);
            //$post = str_replace('/_media/uploads', $newUrl, $post);

            // Check for existing entry
            $command = craft()->db->createCommand();
            $entryRecord =    $command
                        ->select('entryId')
                        ->from('entries_i18n')
                        ->where(array("AND", "slug='" . $importEntry->slug . "'", "sectionId='" . $sectionId . "'"))
                        ->queryRow();

            // If existing entry, load that; Else new entry
            if (is_null($entryRecord['entryId']))
            {
                $entry = new EntryModel();
            }
            else
            {
                $entry = craft()->entries->getEntryById( $entryRecord['entryId'] );
                //echo $entryRecord['entryId'];
            }
            //echo "\n\n";

            /**
             * Set attributes for the EntryModel
             * @ref craft/app/models/EntryModel
             */
            $entry->sectionId = $sectionId;
            $entry->typeId = $typeId;

            /**
             * Attempt to set `entry.authorId` intelligently
             */
            if( isset($importEntry->author) && ! empty($importEntry->author) )
            {
                switch ( $importEntry->author ) 
                {
                    case 'bsteiger@synergema.com':
                        $entry->authorId = 22;
                        break;
                    
                    case 'cdebolt@synergema.com':
                        $entry->authorId = 78;
                        break;

                    default:
                        $entry->authorId = 1;
                        break;
                }
            }
            else
            {
                $entry->authorId = 1;  // Default, admin
            }

            $entry->enabled = true;
            $entry->postDate = $importEntry->entry_date;
            $entry->slug = $importEntry->slug;

            /**
             * Custom field attributes array.
             * @var array
             */
            $attributes = array();

            $attributesMap = array(
                'title'            => 'title',
                //'post'             => 'legacyBody',
                'image'            => 'legacyFeaturedImage',
                'meta_title'       => 'metaSeoTitle',
                'meta_description' => 'metaDescription',
            );

            /**
             * Attributes must be set and not be empty.
             */
            foreach( $attributesMap as $xmlKey => $attributeKey )
            {
                if( isset($importEntry->$xmlKey) && ! empty($importEntry->$xmlKey) )
                {   
                    switch ($xmlKey) 
                    {
                        case 'image':
                            $attributes[$attributeKey] = str_replace('http://synergema.com/_media/uploads', $newUrl, $importEntry->$xmlKey);
                            break;
                        
                        default:
                            $attributes[$attributeKey] = $importEntry->$xmlKey;
                            break;
                    }
                }
            }

            if( isset($importEntry->post) )
            {
                // Add new rows
                $matrixData['newPageContent'] = array(                // The 'new' prefix tells Matrix this is a new block
                    'type' => 'text',
                    'enabled' => true,
                    'fields' => array(
                        'text' => $post,
                    )
                );

                // Set the new Matrix data
                $entry->getContent()->pageContent = $matrixData;
            }

            /**
             * Populate entry custom fields.
             */
            $entry->getContent()->setAttributes($attributes);

            /**
             * Attempt to save entry.
             */
            if ( craft()->entries->saveEntry($entry) )
            {
                // Note that we're doing nothing to limit the number of records processed
                //echo "Entry saved<br />\n\n";
                
                /**
                 * Import Tags.
                 */
                if ( $importTags && is_array( $tagsData ) ) 
                {
                    foreach( $tagsData as $tagElement => $tagData )
                    {
                        $command = craft()->db->createCommand();
                        $entryRecord =  $command
                                        ->select('entryId')
                                        ->from('entries_i18n')
                                        ->where(array("AND", "slug='" . $importEntry->slug . "'", "sectionId='" . $sectionId . "'"))
                                        ->queryRow();

                        $this->entryId = $entryRecord['entryId'];
                        var_export($this->entryId);

                        $tags = array();

                        if(!isset($importEntry->tags->$tagElement->tag))
                        {
                            continue;
                        }

                        foreach( $importEntry->tags->$tagElement->tag as $tagName )
                        {
                            $tag = new TagModel();
                            $tag->setId = $tagData['setId'];
                            $tag->name = $tagName;
                            craft()->tags->saveTag($tag);

                            $command = craft()->db->createCommand();
                            $tagRecord =  $command
                                            ->select('id')
                                            ->from('tags')
                                            ->where("name='" . mysql_real_escape_string( $tagName ) . "'")
                                            ->queryRow();
                            //echo $tagRecord;
                            //echo 'entry: ' . $entryRecord['entryId'] . "<br />";
                            //echo 'tag: ' . $tagRecord['id'] . "<br />";

                            $tags[] = $tagRecord['id'];
                        }
                        craft()->relations->saveRelations($tagData['fieldId'], $entryRecord['entryId'], $tags);
                        //echo "<br />\n\n";
                    }
                }

                /**
                 * Import categories?
                 */
                if( $this->importCategories )
                {
                    $this->saveCategories($importEntry) ;
                }

                continue;
            } 
            else 
            {
                $retVal = false;
                break;
            }
        }

        return $retVal;
    }

    /**
     * Save categories.
     * @param  (int) $entryId          Craft record entryId.
     * @param  (object) $importEntry   XML entry.
     * @return (boolean)               Status.
     */
    private function saveCategories( $xmlEntry = null )
    {
        if( is_null($this->entryId) || is_null($xmlEntry) )
        {
            return false;
        }

        /**
         * Map the category name to the entryId in Craft.
         */
        if( isset( $xmlEntry->tags->categories->tag ) )
        {
            /**
             * Category ID's placeholder.
             * @var array
             */
            $categoryIds = array();

            /**
             * Map of category name to entryId in Craft.
             * @var array
             */
            $categoriesMap = array(
                'case studies'               => 124,
                'internet marketing'         => 125,
                'miscellaneous'              => 126,
                'portfolio'                  => 127,
                'projects'                   => 128,
                'reputation management'      => 129,
                'resources'                  => 130,
                'search engine optimization' => 131,
                'sem'                        => 132,
                'services'                   => 133,
                'social marketing'           => 134,
                'video marketing'            => 135,
                'web development'            => 136,
            );

            /**
             * Loop through and set the categoryId.
             */
            foreach( $xmlEntry->tags->categories->tag as $category )
            {
                $category = strtolower($category);

                if( array_key_exists($category, $categoriesMap) )
                {
                    $categoryIds[] = $categoriesMap[$category];
                }
                else
                {
                    continue;
                }
            }

            if( count($categoryIds) > 0 )
            {
                craft()->relations->saveRelations(30, $this->entryId, $categoryIds);
            }

        }
        else
        {
            return false;
        }

        return true;
    }

    private function saveTags( $entryId = null, $importEntry = null )
    {
    }

}