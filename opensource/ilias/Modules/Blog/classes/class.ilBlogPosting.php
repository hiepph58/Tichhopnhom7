<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once("./Services/COPage/classes/class.ilPageObject.php");

/**
 * Class ilBlogPosting
 *
 * @author Jörg Lützenkirchen <luetzenkirchen@leifos.com>
 * @version $Id$
 *
 * @ingroup ModulesBlog
 */
class ilBlogPosting extends ilPageObject
{	
	protected $title; // [string]
	protected $created; // [ilDateTime]
	protected $blog_node_id; // [int]
	protected $blog_node_is_wsp; // [bool]
	protected $author; // [int]
	protected $approved; // [bool]

	/**
	 * Get parent type
	 *
	 * @return string parent type
	 */
	function getParentType()
	{
		return "blp";
	}	


	/**
	 * Set title
	 *
	 * @param string $a_title
	 */
	function setTitle($a_title)
	{
		$this->title = $a_title;
	}

	/**
	 * Get title
	 *
	 * @return string
	 */
	function getTitle()
	{
		return $this->title;
	}

	/**
	 * Set blog object id
	 *
	 * @param int $a_id
	 */
	function setBlogId($a_id)
	{
		$this->setParentId($a_id);
	}

	/**
	 * Get blog object id
	 *
	 * @return int
	 */
	function getBlogId()
	{
		return $this->getParentId();
	}

	/**
	 * Set creation date
	 *
	 * @param ilDateTime $a_date
	 */
	function setCreated(ilDateTime $a_date)
	{
		$this->created = $a_date;
	}

	/**
	 * Get creation date
	 *
	 * @return ilDateTime
	 */
	function getCreated()
	{
		return $this->created;
	}
	
	/**
	 * Set author user id
	 *
	 * @param int $a_id
	 */
	function setAuthor($a_id)
	{
		$this->author = (int)$a_id;
	}

	/**
	 * Get author user id
	 *
	 * @return int
	 */
	function getAuthor()
	{
		return $this->author;
	}
	
	/**
	 * Toggle approval status
	 */
	function setApproved($a_status)
	{
		$this->approved = (bool)$a_status;
	}

	/**
	 * Get approved status
	 *
	 * @return bool
	 */
	function isApproved()
	{
		return (bool)$this->approved;
	}

	/**
	 * Create new blog posting
	 */
	function create($a_import = false)
	{
		global $ilDB;

		$id = $ilDB->nextId("il_blog_posting");
		$this->setId($id);
		
		if(!$a_import)
		{
			$created = ilUtil::now();
		}
		else
		{
			$created = $this->getCreated()->get(IL_CAL_DATETIME);
		}

		// we are using a separate creation date to enable sorting without JOINs
		
		$query = "INSERT INTO il_blog_posting (id, title, blog_id, created, author, approved)".
			" VALUES (".
			$ilDB->quote($this->getId(), "integer").",".
			$ilDB->quote($this->getTitle(), "text").",".
			$ilDB->quote($this->getBlogId(), "integer").",".
			$ilDB->quote($created, "timestamp").",".
			$ilDB->quote($this->getAuthor(), "integer").",".
			$ilDB->quote($this->isApproved(), "integer").")"; // #16526 - import
		$ilDB->manipulate($query);

		if(!$a_import)
		{
			parent::create();		
			// $this->saveInternalLinks($this->getXMLContent());
		}
	}

	/**
	 * Update blog posting
	 *
	 * @param bool $a_validate
	 * @param bool $a_no_history
	 * @param bool $a_notify
	 * @param string $a_notify_action
	 * @return boolean
	 */
	function update($a_validate = true, $a_no_history = false, $a_notify = true, $a_notify_action = "update")
	{
		global $ilDB;

		// blog_id, author and created cannot be changed
		
		$query = "UPDATE il_blog_posting SET".
			" title = ".$ilDB->quote($this->getTitle(), "text").
			",created = ".$ilDB->quote($this->getCreated()->get(IL_CAL_DATETIME), "text").
			",approved =".$ilDB->quote($this->isApproved(), "integer").
			" WHERE id = ".$ilDB->quote($this->getId(), "integer");
		$ilDB->manipulate($query);
		
		parent::update($a_validate, $a_no_history);		
		
		if($a_notify && $this->getActive())
		{					
			include_once "Modules/Blog/classes/class.ilObjBlog.php";
			ilObjBlog::sendNotification($a_notify_action, $this->blog_node_is_wsp, $this->blog_node_id, $this->getId());
		}

		return true;
	}
	
	/**
	 * Read blog posting
	 */
	function read()
	{
		global $ilDB;
		
		$query = "SELECT * FROM il_blog_posting".
			" WHERE id = ".$ilDB->quote($this->getId(), "integer");
		$set = $ilDB->query($query);
		$rec = $ilDB->fetchAssoc($set);

		$this->setTitle($rec["title"]);
		$this->setBlogId($rec["blog_id"]);
		$this->setCreated(new ilDateTime($rec["created"], IL_CAL_DATETIME));
		$this->setAuthor($rec["author"]);
		if((bool)$rec["approved"])
		{
			$this->setApproved(true);
		}
		
		// when posting is deactivated it should loose the approval
		$this->addUpdateListener($this, "checkApproval");
	
		parent::read();
	}
		
	function checkApproval()
	{
		if(!$this->getActive() && $this->isApproved())
		{
			$this->approved = false;
			$this->update();
		}		
	}

	/**
	 * Delete blog posting and all related data
	 *
	 * @return bool
	 */
	function delete()
	{
		global $ilDB;

		$query = "DELETE FROM il_blog_posting".
			" WHERE id = ".$ilDB->quote($this->getId(), "integer");
		$ilDB->manipulate($query);
		
		parent::delete();

		return true;
	}

	/**
	 * Delete all postings for blog
	 *
	 * @param int $a_blog_id
	 */
	static function deleteAllBlogPostings($a_blog_id)
	{
		global $ilDB;
		
		include_once 'Services/MetaData/classes/class.ilMD.php';
		
		$query = "SELECT * FROM il_blog_posting".
			" WHERE blog_id = ".$ilDB->quote($a_blog_id, "integer");
		$set = $ilDB->query($query);
		while($rec = $ilDB->fetchAssoc($set))
		{			
			// delete all md keywords 
			$md_obj = new ilMD($a_blog_id, $rec["id"], "blp");
			if(is_object($md_section = $md_obj->getGeneral()))
			{
				foreach($md_section->getKeywordIds() as $id)
				{
					$md_key = $md_section->getKeyword($id);				
					$md_key->delete();				
				}
			}
			
			$post = new ilBlogPosting($rec["id"]);
			$post->delete();
		}
	}
	
	/**
	 * Lookup blog id
	 *
	 * @param int $a_posting_id
	 * @return int
	 */
	static function lookupBlogId($a_posting_id)
	{
		global $ilDB;

		$query = "SELECT blog_id FROM il_blog_posting".
			" WHERE id = ".$ilDB->quote($a_posting_id, "integer");
		$set = $ilDB->query($query);
		if ($rec = $ilDB->fetchAssoc($set))
		{
			return $rec["blog_id"];
		}
		return false;
	}

	/**
	 * Get all postings of blog
	 *
	 * @param int $a_blog_id
	 * @param int $a_limit
	 * @param int $a_offset
	 * @return array
	 */
	static function getAllPostings($a_blog_id, $a_limit = 1000, $a_offset = 0)
	{
		global $ilDB;
		
		$pages = parent::getAllPages("blp", $a_blog_id);

		if($a_limit)
		{
			$ilDB->setLimit($a_limit, $a_offset);
		}
		
		$query = "SELECT * FROM il_blog_posting".
			" WHERE blog_id = ".$ilDB->quote($a_blog_id, "integer").
			" ORDER BY created DESC";
		$set = $ilDB->query($query);
		$post = array();
		while($rec = $ilDB->fetchAssoc($set))
		{
			if (isset($pages[$rec["id"]]))
			{
				$post[$rec["id"]] = $pages[$rec["id"]];
				$post[$rec["id"]]["title"] = $rec["title"];
				$post[$rec["id"]]["created"] = new ilDateTime($rec["created"], IL_CAL_DATETIME);
				$post[$rec["id"]]["author"] = $rec["author"];
				$post[$rec["id"]]["approved"] = (bool)$rec["approved"];
								
				foreach(self::getPageContributors("blp", $rec["id"]) as $editor)
				{
					if($editor["user_id"] != $rec["author"])
					{
						$post[$rec["id"]]["editors"][] = $editor["user_id"];
					}
				}
			}
		}

		return $post;
	}

	/**
	 * Checks whether a posting exists
	 *
	 * @param int $a_blog_id
	 * @param int $a_posting_id
	 * @return bool
	 */
	static function exists($a_blog_id, $a_posting_id)
	{
		global $ilDB;

		$query = "SELECT id FROM il_blog_posting".
			" WHERE blog_id = ".$ilDB->quote($a_blog_id, "integer").
			" AND id = ".$ilDB->quote($a_posting_id, "integer");
		$set = $ilDB->query($query);
		if($rec = $ilDB->fetchAssoc($set))
		{
			return true;
		}
		return false;
	}

	/**
	 * Get newest posting for blog
	 *
	 * @param int $a_blog_id
	 * @return int
	 */
	static function getLastPost($a_blog_id)
	{
		$data = self::getAllPostings($a_blog_id, 1);
		if($data)
		{
			return array_pop(array_keys($data));
		}
	}
	
	/**
	 * Set blog node id (needed for notification)
	 * 
	 * @param int $a_id
	 * @param bool $a_is_in_workspace
	 */
	public function setBlogNodeId($a_id, $a_is_in_workspace = false)
	{
		$this->blog_node_id = (int)$a_id;
		$this->blog_node_is_wsp = (bool)$a_is_in_workspace;
	}
	
	/**
	 * Get all blogs where user has postings
	 * 
	 * @param int $a_user_id
	 * @return array
	 */
	public static function searchBlogsByAuthor($a_user_id)
	{
		global $ilDB;
		
		$ids = array();
		
		$sql = "SELECT DISTINCT(blog_id)".
			" FROM il_blog_posting".
			" WHERE author = ".$ilDB->quote($a_user_id);
		$set = $ilDB->query($sql);
		while($row = $ilDB->fetchAssoc($set))
		{
			$ids[] = $row["blog_id"];
		}
		return $ids;
	}
	
	public function getNotificationAbstract()
	{	
		include_once "Modules/Blog/classes/class.ilBlogPostingGUI.php";
		$snippet = ilBlogPostingGUI::getSnippet($this->getId(), true);
		
		// making things more readable
		$snippet = str_replace('<br/>', "\n", $snippet);
		$snippet = str_replace('<br />', "\n", $snippet);
		$snippet = str_replace('</p>', "\n", $snippet);
		$snippet = str_replace('</div>', "\n", $snippet);
	
		return trim(strip_tags($snippet));			
	}
	
	
	// keywords
	
	public function getMDSection()
	{											
		// general section available?
		include_once 'Services/MetaData/classes/class.ilMD.php';
		$md_obj = new ilMD($this->getBlogId(), $this->getId(), "blp");
		if(!is_object($md_section = $md_obj->getGeneral()))
		{
			$md_section = $md_obj->addGeneral();
			$md_section->save();
		}						
		
		return $md_section;
	}
		
	public function updateKeywords(array $keywords)
	{
		global $ilUser;
				
		// language is not "used" anywhere
		$ulang = $ilUser->getLanguage();
		$keywords = array($ulang=>$keywords);

		include_once("./Services/MetaData/classes/class.ilMDKeyword.php");				
		ilMDKeyword::updateKeywords($this->getMDSection(), $keywords);		
	}
		
	public static function getKeywords($a_obj_id, $a_posting_id)
	{
		include_once("./Services/MetaData/classes/class.ilMDKeyword.php");
		return ilMDKeyword::lookupKeywords($a_obj_id, $a_posting_id);
	}	
}

?>