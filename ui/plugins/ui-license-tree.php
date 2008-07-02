<?php
/***********************************************************
 Copyright (C) 2008 Hewlett-Packard Development Company, L.P.

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
***********************************************************/

/*************************************************
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 *************************************************/
global $GlobalReady;
if (!isset($GlobalReady)) { exit; }

class ui_license_tree extends FO_Plugin
  {
  var $Name       = "license-tree";
  var $Title      = "License Tree View";
  var $Version    = "1.0";
  var $Dependency = array("db","browse","license","view-license");
  var $DBaccess   = PLUGIN_DB_READ;
  var $LoginFlag  = 0;

  /***********************************************************
   RegisterMenus(): Customize submenus.
   ***********************************************************/
  function RegisterMenus()
    {
    // For all other menus, permit coming back here.
    $URI = $this->Name . Traceback_parm_keep(array("show","format","page","upload","item","ufile","pfile"));
    $Item = GetParm("item",PARM_INTEGER);
    $Upload = GetParm("upload",PARM_INTEGER);
    if (!empty($Item) && !empty($Upload))
      {
      if (GetParm("mod",PARM_STRING) == $this->Name)
	{
	menu_insert("Browse::License Tree",1);
	menu_insert("Browse::[BREAK]",100);
	menu_insert("Browse::Clear",101,NULL,NULL,NULL,"<a href='javascript:LicColor(\"\",\"\",\"\",\"\");'>Clear</a>");
	}
      else
	{
	menu_insert("Browse::License Tree",1,$URI,"View license tree");
	}
      }
    } // RegisterMenus()

  /***********************************************************
   SortName(): Given two elements sort them by name.
   Used for sorting the histogram.
   ***********************************************************/
  function SortName ($a,$b)
    {
    list($A0,$A1,$A2) = split("\|",$a,3);
    list($B0,$B1,$B2) = split("\|",$b,3);
    /* Sort by count */
    if ($A0 < $B0) { return(1); }
    if ($A0 > $B0) { return(-1); }
    /* Same count? sort by root name.
       Same root? place real before style before partial. */
    $A0 = str_replace('-partial$',"",$A1);
    if ($A0 != $A1) { $A1 = '-partial'; }
    else
      {
      $A0 = str_replace('-style',"",$A1);
      if ($A0 != $A1) { $A1 = '-style'; }
      else { $A1=''; }
      }
    $B0 = str_replace('-partial$',"",$B1);
    if ($B0 != $B1) { $B1 = '-partial'; }
    else
      {
      $B0 = str_replace('-style',"",$B1);
      if ($B0 != $B1) { $B1 = '-style'; }
      else { $B1=''; }
      }
    if ($A0 != $B0) { return(strcmp($A0,$B0)); }
    if ($A1 == "") { return(-1); }
    if ($B1 == "") { return(1); }
    if ($A1 == "-partial") { return(-1); }
    if ($B1 == "-partial") { return(1); }
    return(strcmp($A1,$B1));
    } // SortName()

  /***********************************************************
   ShowLicenseTree(): Given an Upload and UploadtreePk item, display:
   (1) The file listing for the directory, with license navigation.
   (2) Recursively traverse the tree.
   NOTE: This is recursive!
   NOTE: Output goes to stdout!
   ***********************************************************/
  function ShowLicenseTree($Upload,$Item,$Uri,$Path=NULL)
    {
    /*****
     Get all the licenses PER item (file or directory) under this
     UploadtreePk.
     Save the data 3 ways:
       - Number of licenses PER item.
       - Number of items PER license.
       - Number of items PER license family.
     *****/
    global $Plugins;
    global $DB;
    $Time = time();
    $ModLicView = &$Plugins[plugin_find_id("view-license")];
    if ($Path == NULL) { $Path = array(); }

    /****************************************/
    /* Get the items under this UploadtreePk */
    $Children = DirGetList($Upload,$Item);
    $Name="";
    foreach($Children as $C)
      {
      if (empty($C)) { continue; }
      /* Store the item information */
      $IsDir = Isdir($C['ufile_mode']);
      $IsContainer = Iscontainer($C['ufile_mode']);
      $IsArtifact = Isartifact($C['ufile_mode']);

      /* Load licenses for the item */
      $Lics = array();
      if ($IsContainer) { LicenseGetAll($C['uploadtree_pk'],$Lics); }
      else { LicenseGet($C['pfile_fk'],$Lics); }

      /* Determine the hyperlinks */
      if (!empty($C['pfile_fk']))
	{
	$LinkUri = "$Uri&item=$Item&ufile=" . $C['ufile_pk'] . "&pfile=" . $C['pfile_fk'];
	$LinkUri = str_replace("mod=license-tree","mod=view-license",$LinkUri);
	}
      else
	{
	$LinkUri = NULL;
	}

      if (Iscontainer($C['ufile_mode']))
	{
	$LicUri = "$Uri&item=" . DirGetNonArtifact($C['uploadtree_pk']);
	$LicUri = str_replace("mod=license-tree","mod=license",$LicUri);
	}
      else
	{
	$LicUri = NULL;
	}

      /* Populate the output */
      ksort($Lics);
      $LicCount = $Lics[' Total '];
      $LicSum = "";
      foreach($Lics as $Key => $Val)
        {
	if (!empty($LicSum)) { $LicSum .= ","; }
	$LicSum .= $Key;
	}

      /* Display the results */
      if ($LicCount > 0)
	{
	print "<tr><td align='right' width='10%' valign='top'>";
	print " [" . number_format($LicCount,0,"",",") . "&nbsp;";
	print "license" . ($LicCount == 1 ? "" : "s");
	print "</a>";
	print "]";

	/* Compute license summary */
	print "</td><td width='1%'></td><td width='10%' valign='top'>";
	$LicSum = "";
	foreach($Lics as $Key => $Val)
	  {
	  if ($Key == " Total ") { continue; }
	  if (!empty($LicSum)) { $LicSum .= ","; }
	  $LicSum .= $Key;
	  }
	print htmlentities($LicSum);

        /* Show the history path */
	print "</td><td width='1%'></td><td valign='top'>";
        for($i=0; !empty($Path[$i]); $i++) { print $Path[$i]; }

	$HasHref=0;
	if ($IsContainer)
	  {
	  print "<a href='$LicUri'>";
	  $HasHref=1;
	  }
	else if (!empty($LinkUri))
	  {
	  print "<a href='$LinkUri'>";
	  $HasHref=1;
	  }
	if ($IsContainer) { print "<b>"; };
	$Name = $C['ufile_name'];
	if ($IsArtifact) { $Name = str_replace("artifact.","",$Name); }
	print $Name;
	if ($IsContainer) { print "</b>"; };
	if ($IsDir)
	  {
	  print "/";
	  $Name .= "/";
	  }
	else if ($IsContainer) { $Name .= " :: "; }
	if ($HasHref) { print "</a>"; }
	print "</td></tr>";
	}

      /* Recurse! */
      if (($IsDir || $IsContainer) && ($LicCount > 0))
        {
        $NewPath = $Path;
	$NewPath[] = $Name;
	$this->ShowLicenseTree($Upload,$C['uploadtree_pk'],$Uri,$NewPath);
	}
      } /* for each item in the directory */
    flush();
    } // ShowLicenseTree()

  /***********************************************************
   Output(): This function returns the scheduler status.
   ***********************************************************/
  function Output()
    {
    if ($this->State != PLUGIN_STATE_READY) { return(0); }
    $V="";
    $Folder = GetParm("folder",PARM_INTEGER);
    $Upload = GetParm("upload",PARM_INTEGER);
    $Item = GetParm("item",PARM_INTEGER);

    switch(GetParm("show",PARM_STRING))
	{
	case 'detail':
		$Show='detail';
		break;
	case 'summary':
	default:
		$Show='summary';
		break;
	}

    switch($this->OutputType)
      {
      case "XML":
	break;
      case "HTML":
	$V .= "<font class='text'>\n";

	/************************/
	/* Show the folder path */
	/************************/
	$V .= Dir2Browse($this->Name,$Item,-1,NULL,1,"Browse") . "<P />\n";

	/******************************/
	/* Get the folder description */
	/******************************/
	if (!empty($Folder))
	  {
	  // $V .= $this->ShowFolder($Folder);
	  }
	if (!empty($Upload))
	  {
	  print $V; $V="";
	  $Uri = preg_replace("/&item=([0-9]*)/","",Traceback());
	  print "<table border='0' width='100%'>";
	  $this->ShowLicenseTree($Upload,$Item,$Uri);
	  print "</table>";
	  }
	$V .= "</font>\n";
	break;
      case "Text":
	break;
      default:
	break;
      }
    if (!$this->OutputToStdout) { return($V); }
    print "$V";
    return;
    }

  };
$NewPlugin = new ui_license_tree;
$NewPlugin->Initialize();

?>
