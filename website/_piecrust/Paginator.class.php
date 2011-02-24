<?php

require_once 'FileSystem.class.php';


/**
 * The pagination manager for a page split into sub-pages.
 *
 * Pages that display a large number of posts may be split into
 * several sub-pages. The Paginator class handles figuring out what
 * posts to include for the current page number.
 *
 */
class Paginator
{
    protected $pieCrust;
	protected $pageUri;
	protected $pageNumber;
    
	/**
	 * Creates a new Paginator instance.
	 */
    public function __construct(PieCrust $pieCrust, Page $page)
    {
        $this->pieCrust = $pieCrust;
		$this->pageUri = $page->getUri();
		$this->pageNumber = $page->getPageNumber();
    }
	
	/**
	 * Gets the posts for this page.
	 *
	 * This method is meant to be called from the layouts via the template engine.
	 */
	public function posts()
	{
		$pagination = $this->getPaginationData();
		return $pagination['posts'];
	}
	
	/**
	 * Gets the previous page's URI.
	 *
	 * This method is meant to be called from the layouts via the template engine.
	 */
	public function prev_page()
	{
		$pagination = $this->getPaginationData();
		return $pagination['prev_page'];
	}
	
	/**
	 * Gets this page's URI.
	 *
	 * This method is meant to be called from the layouts via the template engine.
	 */
	public function this_page()
	{
		$pagination = $this->getPaginationData();
		return $pagination['this_page'];
	}
	
	/**
	 * Gets thenext page's URI.
	 *
	 * This method is meant to be called from the layouts via the template engine.
	 */
	public function next_page()
	{
		$pagination = $this->getPaginationData();
		return $pagination['next_page'];
	}
	
	/**
	 * Gets whether the pagination data was requested by the page.
	 */
	public function wasPaginationDataAccessed()
	{
		return ($this->paginationData != null);
	}
	
	/**
	 * Gets whether the current page has more pages to show.
	 */
	public function hasMorePages()
	{
		return ($this->next_page() != null);
	}
	
	protected $paginationData;
    /**
	 * Gets the pagination data for rendering.
	 */
    public function getPaginationData()
	{
		if ($this->paginationData === null)
		{
			$postsData = array();
			$nextPageIndex = null;
			$previousPageIndex = ($this->pageNumber > 2) ? $this->pageNumber - 1 : null;
			
			// Find all HTML posts in the posts directory.
			$fs = new FileSystem($this->pieCrust);
			$postsFs = $this->pieCrust->getConfigValue('site', 'posts_fs');
			switch ($postsFs)
			{
			case 'hierarchy':
				$postInfos = $fs->getHierarchicalPostFiles();
				break;
			case 'flat':
			default:
				$postInfos = $fs->getFlatPostFiles();
				break;
			}
			if (count($postInfos) > 0)
			{
				// Load all the posts for the requested page number (page numbers start at '1').
				$postsPrefix = $this->pieCrust->getConfigValue('site', 'posts_prefix');
				$postsUrlFormat = $this->pieCrust->getConfigValue('site', 'posts_urls');
				$postsPerPage = $this->pieCrust->getConfigValue('site', 'posts_per_page');
				$postsDateFormat = $this->pieCrust->getConfigValue('site', 'posts_date_format');
				$offset = ($this->pageNumber - 1) * $postsPerPage;
				$upperLimit = min($offset + $postsPerPage, count($postInfos));
				for ($i = $offset; $i < $upperLimit; ++$i)
				{
					$postInfo = $postInfos[$i];
					// Create the post with all the stuff we already know.
					$post = Page::create(
						$this->pieCrust,
						Paginator::buildPostUrl($postsPrefix, $postsUrlFormat, $postInfo), 
						$postInfo['path'],
						true);

					// Build the pagination data entry for this post.
					$postData = $post->getConfig();
					$postData['url'] = $post->getUri();
					
					$postDateTime = strtotime($postInfo['year'] . '-' . $postInfo['month'] . '-' . $postInfo['day']);
					$postData['date'] = date($postsDateFormat, $postDateTime);
					
					$postContents = $post->getContents();
					$postContentsSplit = preg_split('/^<!--\s*(more|(page)?break)\s*-->\s*$/m', $postContents, 2);
					$postData['content'] = $postContentsSplit[0];
					
					$postsData[] = $postData;
				}
				
				if ($offset + $postsPerPage < count($postInfos))
				{
					// There's another page following this one.
					$nextPageIndex = $this->pageNumber + 1;
				}
			}
			
			$this->paginationData = array(
									'posts' => $postsData,
									'prev_page' => ($previousPageIndex == null) ? null : $this->pageUri . '/' . $previousPageIndex,
									'this_page' => $this->pageUri . '/' . $this->pageNumber,
									'next_page' => ($nextPageIndex == null) ? null : ($this->pageUri . '/' . $nextPageIndex)
									);
		}
        return $this->paginationData;
    }
	
	/**
	 * Builds the URL of a post given a URL format.
	 */
	public static function buildPostUrl($postPrefix, $postUrlFormat, $postInfo)
	{
		$replacements = array(
			'%year%' => $postInfo['year'],
			'%month%' => $postInfo['month'],
			'%day%' => $postInfo['day'],
			'%slug%' => $postInfo['name']
		);
		if ($postPrefix != '')
		{
			$postPrefix = rtrim($postPrefix, '/') . '/';
		}
		return $postPrefix . str_replace(array_keys($replacements), array_values($replacements), $postUrlFormat);
	}
}