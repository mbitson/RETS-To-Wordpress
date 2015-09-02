<?php

class WPManager
{
	// Must set wp-load location relative to this script!!
	private $wpInclude = '../wp-load.php';

	/**
	 * Construct file that will include the wordpress core.
	 */
	public function __construct()
	{
		// Tell wordpress to not use themes.
		define('WP_USE_THEMES', false);

		// Define wordpress globals.
		global $wp, $wp_query, $wp_the_query, $wp_rewrite, $wp_did_header;

		// Require wordpress files.
		require $this->wpInclude;
	}

	/**
	 * Function to delete all posts in wordpress.
	 * Optionally, filter by post type.
	 * Also optionally, delete all attachments.
	 *
	 * @param null $postType
	 * @param bool $deleteAttachments
	 *
	 * @return array
	 */
	public function deletePosts($postType = NULL, $deleteAttachments = FALSE)
	{
		// Init results array
		$results = array();

		// Get posts
		$posts = get_posts(array(
			'post_type'     => (!is_null($postType)?$postType:'post'),
			'posts_per_page' => -1,
			'post_status'   => 'publish'
		));

		// For every post...
		foreach($posts as $post)
		{
			// If we are to delete all attachments...
			if($deleteAttachments)
			{
				// Delete this posts' attachments.
				if($result = $this->deleteAttachments($post->ID))
				{
					// Add results to result array.
					$results['attachments'][$post->ID] = $result;
				}
			}

			// Now delete the post..
			if($result = wp_delete_post($post->ID, true))
			{
				// Add this post to results.
				$results['posts'][] = $post->ID;
			}
		}

		// Return results.
		return $results;
	}

	/**
	 * Delete all wordpress attachments
	 * for a particular post.
	 *
	 * @param null $postId
	 *
	 * @return array
	 */
	public function deleteAttachments($postId = NULL)
	{
		// Data must be sent
		if(is_null($postId)){
			return 'Must provide post ID to delete attachments.';
		}

		// Init a results array
		$results = array();

		// Get attachments
		$attachments =  get_posts(array(
			'post_type' => 'attachment',
			'posts_per_page' => -1,
			'post_status' => 'any',
			'post_parent' => $postId
		));

		// Loop through attachments
		foreach($attachments as $attachment)
		{
			// Delete this attachment.
			$result = wp_delete_attachment($attachment->ID, true);

			// If deletion was successful...
			if($result)
			{
				// Add id to success array.
				$results[] = $attachment->ID;
			}

			// Else, deletion failed...
			else
			{
				// Warn the user...
				echo "Warning! : Attachment could not be deleted: ".$attachment->ID;
			}
		}

		// Return results.
		return $results;
	}

	/**
	 * Function to create a post in wordpress.
	 *
	 * @param       $title       - Post Title
	 * @param       $content     - Post Description/WYSIWYG
	 * @param       $postType    - Post Type, typically 'property'
	 * @param       $metas       - Array of meta keys and values for this post.
	 * @param null  $date        - Optional date of listing.
	 * @param null  $images      - Optional array of images to attach. Image array should have a key ['Location'] for URL of image.
	 *
	 * @return bool|int|WP_Error
	 */
	function createPost($title, $content, $postType, $metas, $date = NULL, $images = NULL)
	{
		global $user_ID, $wpdb;

		// Create exists flag.
		$exists = false;

		// If the MLS ID is passed, check to see if we already have the post...
		if(isset($metas['mls_id']))
		{
			// Configure meta query
			$meta_query_args = array(
				'relation' => 'AND',
				array(
					'key'     => 'mls_id',
					'value'   => $metas['mls_id'],
					'compare' => '='
				)
			);

			// Configure post query
			$args = array(
				'post_type' => $postType,
				'posts_per_page' => -1,
				'order' => 'ASC',
				'meta_query' => $meta_query_args
			);

			// Run query
			$propertyResult = new WP_Query($args);

			// If one property is returned..
			if($propertyResult->post_count === 1)
			{
				// Tell the script that it exists.
				$exists = $propertyResult->post->ID;
			}
		}

		// If this post does not exist...
		if($exists === FALSE)
		{
			// Create arguements for inserting
			$insertArgs = array (
				'post_type' => $postType,
				'post_title' => $title,
				'post_content' => $content,
				'post_status' => 'publish',
				'comment_status' => 'closed',   // if you prefer
				'ping_status' => 'closed'      // if you prefer
			);

			// If a date was passed...
			if(!is_null($date))
			{
				// Change the post date to passed date...
				$insertArgs['post_date'] = $date;
			}

			// Insert the post
			$post_id = wp_insert_post($insertArgs);

			//Debug!
			echo "<ul>";
			echo "<li><strong>";
			echo $post_id;
			echo "</strong></li>";

			// If post was inserted...
			if ($post_id)
			{
				// If meta data was passed
				if(isset($metas) && count($metas) >= 1)
				{
					// Loop through meta data
					foreach($metas as $meta => $value)
					{
						// And add the metadata to the post.
						add_post_meta($post_id, $meta, $value, true) || update_post_meta($post_id, $meta, $value);

						//Debug!
						echo "<li>";
						echo $meta." - ".$value;
						echo "</li>";
					}
				}

				// If images were passed
				if(isset($images) && count($images) >= 1)
				{
					// Loop through meta data
					foreach($images as $image)
					{
						// Add this image to the post.
						$this->addImageToPost($image['Location'], $post_id);

						//Debug!
						echo "<li>";
						echo $post_id." Image - ".$image['Location'];
						echo "</li>";
					}
				}

				//Debug!
				echo "</ul>";
				return $post_id;
			}

			// Else if insert faled..
			else
			{
				return FALSE;
			}
		}

		// Else if this post already existed...
		else
		{
			echo "Post #".$propertyResult->post->ID." already existed... try update!<br />";
			return false;
		}
	}

	/**
	 * Function to insert an image into wordpress
	 * as an attachment and link it to a specific
	 * post.
	 *
	 * @param $myimageurl
	 * @param $mypostid
	 */
	function addImageToPost($myimageurl, $mypostid)
	{
		// Add Featured Image to Post
		$image_url  = $myimageurl; // Define the image URL here
		$post_id	= $mypostid;
		$image_url = substr($image_url, 0, -4);
		$imageCounter = 1;
		$status = true;
		$allImages = array();
		$allImages[] = $myimageurl;
		while($status){
			if(strlen($imageCounter)==1){ $imageCounter = '0'.$imageCounter; }
			$url = $image_url."_".$imageCounter.".jpg";
			$response = get_headers($url);
			if($response[0] == 'HTTP/1.1 200 OK'){
				$allImages[] = $url;
				$imageCounter++;
			}else{
				$status = false;
			}
		}
		foreach($allImages as $key => $image_url){
			$upload_dir = wp_upload_dir(); // Set upload folder
			$image_data = file_get_contents($image_url); // Get image data
			$filename   = basename($image_url); // Create image file name
			if($image_data){
				if($key == 0 && has_post_thumbnail($post_id)){
				}else{
					// Check folder permission and define file location
					if( wp_mkdir_p( $upload_dir['path'] ) ) {
						$file = $upload_dir['path'] . '/' . $filename;
					} else {
						$file = $upload_dir['basedir'] . '/' . $filename;
					}
					// Create the image  file on the server
					file_put_contents( $file, $image_data );
					// Check image file type
					$wp_filetype = wp_check_filetype( $filename, null );
					// Set attachment data
					$attachment = array('post_mime_type' => $wp_filetype['type'],'post_title'     => sanitize_file_name( $filename ),
					                    'post_content'   => '','post_status'    => 'inherit');
					// Create the attachment
					$attach_id = wp_insert_attachment( $attachment, $file, $post_id );
					// Include image.php
					require_once(ABSPATH . 'wp-admin/includes/image.php');
					// Define attachment metadata
					$attach_data = wp_generate_attachment_metadata( $attach_id, $file );
					// Assign metadata to attachment
					wp_update_attachment_metadata( $attach_id, $attach_data );
					if(!has_post_thumbnail( $post_id ) && $key == 0){
						// And finally assign featured image to post
						set_post_thumbnail( $post_id, $attach_id );
					}
				}
			}
		}
	}

	/**
	 * Function to search all wordpress users by name (and row)
	 *
	 * @param null   $name
	 * @param string $role
	 *
	 * @return bool|string
	 */
	function getUserByName($name = NULL, $role = 'author')
	{
		// Make sure name is passed...
		if(is_null($name))
		{
			return "You must provide a name to search.";
		}

		// Get all agents from wordpress
		$users = get_users(
			array(
				'role' => $role
			)
		);

		// For each user
		foreach($users as $user)
		{
			// If the search name is in their display name...
			if(strstr($user->data->display_name,$name))
			{
				// Return this user ID!
				return $user->data->ID;
			}
		}

		// If no user is found, return false.
		return FALSE;
	}
}