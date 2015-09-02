<?php
/**
 * Created by PhpStorm.
 * User: mbitson
 * Date: 9/29/2014
 * Time: 8:58 AM
 * This scrip allows for a complete deletion of
 * all posts with the type 'property'. It then
 * pulls all relavent MLS information from the
 * server and presents it.
 */

// Include configuration
require_once('rets-init.php');

// Delete all current properties from the site!
$wp->deletePosts('property', true);

// Build residential search query
$residentialResults = $rets->search('Property', 'A', '%28LIST_106=20140618172653797612000000%29,%28LIST_87=1960-01-01T00:00:00%2B%29', null, true);

// Build commercial search query
$lotsAndLandResults = $rets->search('Property', 'B', '%28LIST_106=20140618172653797612000000%29,%28LIST_87=1960-01-01T00:00:00%2B%29', null, true);

// Build commercial search query
$commercialResults = $rets->search('Property', 'C', '%28LIST_106=20140618172653797612000000%29,%28LIST_87=1960-01-01T00:00:00%2B%29', null, true);

// Build commercial search query
$multiFamilyResults = $rets->search('Property', 'E', '%28LIST_106=20140618172653797612000000%29,%28LIST_87=1960-01-01T00:00:00%2B%29', null, true);

// Properties to process array
$processQueue = array(
	$commercialResults->REData->REProperties->CommonInterest,
	$lotsAndLandResults->REData->REProperties->LotsAndLand,
	$multiFamilyResults->REData->REProperties->MultiFamily,
	$residentialResults->REData->REProperties->ResidentialProperty
);

// Loop through process queue.
foreach($processQueue as $queueItem)
{
	// Loop through all of the query results. 
	foreach ($queueItem as $result)
	{
		if(empty($result->Listing->MLSInformation)){
			$result = $result->ResidentialProperty;
		}
		if(
			(string)$result->Listing->MLSInformation->ListingStatus[0] !== 'Closed'
		)
		{
			// Get property agent
			$agentID = $wp->getUserByName((string) $result->Listing->ListingData->REAgent->LastName);

			// Build search query
			$property = $rets->formatForNexes($result, $agentID);

			// Get image object
			$imageHeaders = $rets->getObject('HiRes', 'Property', $result->Listing->ListingID . ':*', 1);
			$images       = $rets->parseImageHeaders($imageHeaders);

			// Create posts!
			$wp->createPost($property['title'], $property['content'], $property['postType'], $property['meta'], null, $images);
		}
	}
}
?>