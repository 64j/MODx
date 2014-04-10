<?php
if (IN_MANAGER_MODE != "true")
  die("<b>INCLUDE_ORDERING_ERROR</b><br /><br />Please use the MODX Content Manager instead of accessing this file directly.");

/********************/
$sd       = isset($_REQUEST['dir']) ? '&dir=' . $_REQUEST['dir'] : '&dir=DESC';
$sb       = isset($_REQUEST['sort']) ? '&sort=' . $_REQUEST['sort'] : '&sort=createdon';
$pg       = isset($_REQUEST['page']) ? '&page=' . (int) $_REQUEST['page'] : '';
$add_path = $sd . $sb . $pg;
/*******************/

// check permissions
switch ($_REQUEST['a']) {
  case 27:
    if (!$modx->hasPermission('edit_document')) {
      $modx->webAlertAndQuit($_lang["error_no_privileges"]);
    }
    break;
  case 85:
  case 72:
  case 4:
    if (!$modx->hasPermission('new_document')) {
      $modx->webAlertAndQuit($_lang["error_no_privileges"]);
    } elseif (isset($_REQUEST['pid']) && $_REQUEST['pid'] != '0') {
      // check user has permissions for parent
      include_once(MODX_MANAGER_PATH . 'processors/user_documents_permissions.class.php');
      $udperms           = new udperms();
      $udperms->user     = $modx->getLoginUserID();
      $udperms->document = empty($_REQUEST['pid']) ? 0 : $_REQUEST['pid'];
      $udperms->role     = $_SESSION['mgrRole'];
      if (!$udperms->checkPermissions()) {
        $modx->webAlertAndQuit($_lang["access_permission_denied"]);
      }
    }
    break;
  default:
    $modx->webAlertAndQuit($_lang["error_no_privileges"]);
}


if (isset($_REQUEST['id']))
  $id = (int) $_REQUEST['id'];
else
  $id = 0;

// Get table names (alphabetical)
$tbl_active_users               = $modx->getFullTableName('active_users');
$tbl_categories                 = $modx->getFullTableName('categories');
$tbl_document_group_names       = $modx->getFullTableName('documentgroup_names');
$tbl_member_groups              = $modx->getFullTableName('member_groups');
$tbl_membergroup_access         = $modx->getFullTableName('membergroup_access');
$tbl_document_groups            = $modx->getFullTableName('document_groups');
$tbl_keyword_xref               = $modx->getFullTableName('keyword_xref');
$tbl_site_content               = $modx->getFullTableName('site_content');
$tbl_site_content_metatags      = $modx->getFullTableName('site_content_metatags');
$tbl_site_keywords              = $modx->getFullTableName('site_keywords');
$tbl_site_metatags              = $modx->getFullTableName('site_metatags');
$tbl_site_templates             = $modx->getFullTableName('site_templates');
$tbl_site_tmplvar_access        = $modx->getFullTableName('site_tmplvar_access');
$tbl_site_tmplvar_contentvalues = $modx->getFullTableName('site_tmplvar_contentvalues');
$tbl_site_tmplvar_templates     = $modx->getFullTableName('site_tmplvar_templates');
$tbl_site_tmplvars              = $modx->getFullTableName('site_tmplvars');

if ($action == 27) {
  //editing an existing document
  // check permissions on the document
  include_once(MODX_MANAGER_PATH . 'processors/user_documents_permissions.class.php');
  $udperms           = new udperms();
  $udperms->user     = $modx->getLoginUserID();
  $udperms->document = $id;
  $udperms->role     = $_SESSION['mgrRole'];
  
  if (!$udperms->checkPermissions()) {
    $modx->webAlertAndQuit($_lang["access_permission_denied"]);
  }
}

// Check to see the document isn't locked
$rs = $modx->db->select('username', $tbl_active_users, "action=27 AND id='{$id}' AND internalKey!='" . $modx->getLoginUserID() . "'");
if ($username = $modx->db->getValue($rs)) {
  $modx->webAlertAndQuit(sprintf($_lang['lock_msg'], $username, 'document'));
}

// get document groups for current user
if ($_SESSION['mgrDocgroups']) {
  $docgrp = implode(',', $_SESSION['mgrDocgroups']);
}

if (!empty($id)) {
  $access  = "1='" . $_SESSION['mgrRole'] . "' OR sc.privatemgr=0" . (!$docgrp ? '' : " OR dg.document_group IN ($docgrp)");
  $rs      = $modx->db->select('sc.*', "{$tbl_site_content} AS sc LEFT JOIN {$tbl_document_groups} AS dg ON dg.document=sc.id", "sc.id='{$id}' AND ({$access})");
  $content = $modx->db->getRow($rs);
  if (!$content) {
    $modx->webAlertAndQuit($_lang["access_permission_denied"]);
  }
  $_SESSION['itemname'] = $content['pagetitle'];
} else {
  $content              = array();
  $_SESSION['itemname'] = $_lang["new_resource"];
}

// restore saved form
$formRestored = false;
if ($modx->manager->hasFormValues()) {
  $modx->manager->loadFormValues();
  $formRestored = true;
}

// retain form values if template was changed
// edited to convert pub_date and unpub_date
// sottwell 02-09-2006
if ($formRestored == true || isset($_REQUEST['newtemplate'])) {
  $content            = array_merge($content, $_POST);
  $content['content'] = $_POST['ta'];
  if (empty($content['pub_date'])) {
    unset($content['pub_date']);
  } else {
    $content['pub_date'] = $modx->toTimeStamp($content['pub_date']);
  }
  if (empty($content['unpub_date'])) {
    unset($content['unpub_date']);
  } else {
    $content['unpub_date'] = $modx->toTimeStamp($content['unpub_date']);
  }
}

// increase menu index if this is a new document
if (!isset($_REQUEST['id'])) {
  if (!isset($auto_menuindex) || $auto_menuindex) {
    $pid                  = intval($_REQUEST['pid']);
    $rs                   = $modx->db->select('count(*)', $tbl_site_content, "parent='{$pid}'");
    $content['menuindex'] = $modx->db->getValue($rs);
  } else {
    $content['menuindex'] = 0;
  }
}

if (isset($_POST['which_editor'])) {
  $which_editor = $_POST['which_editor'];
}
?>
<script type="text/javascript" src="media/calendar/datepicker.js"></script>
<script type="text/javascript">
/* <![CDATA[ */
window.addEvent('domready', function(){
    var dpOffset = <?php
echo $modx->config['datepicker_offset'];
?>;
    var dpformat = "<?php
echo $modx->config['datetime_format'];
?>" + ' hh:mm:00';
    new DatePicker($('pub_date'), {'yearOffset': dpOffset,'format':dpformat});
    new DatePicker($('unpub_date'), {'yearOffset': dpOffset,'format':dpformat});

    if( !window.ie6 ) {
        $$('img[src=<?php
echo $_style["icons_tooltip_over"];
?>]').each(function(help_img) {
            help_img.removeProperty('onclick');
            help_img.removeProperty('onmouseover');
            help_img.removeProperty('onmouseout');
            help_img.setProperty('title', help_img.getProperty('alt') );
            help_img.setProperty('class', 'tooltip' );
            if (window.ie) help_img.removeProperty('alt');
        });
        new Tips($$('.tooltip'),{className:'custom'} );
    }
});

// save tree folder state
if (parent.tree) parent.tree.saveFolderState();

function changestate(element) {
    currval = eval(element).value;
    if (currval==1) {
        eval(element).value=0;
    } else {
        eval(element).value=1;
    }
    documentDirty=true;
}

function deletedocument() {
    if (confirm("<?php
echo $_lang['confirm_delete_resource'];
?>")==true) {
        document.location.href="index.php?id=" + document.mutate.id.value + "&a=6<?php
echo $add_path;
?>";
    }
}

function duplicatedocument(){
    if(confirm("<?php
echo $_lang['confirm_resource_duplicate'];
?>")==true) {
        document.location.href="index.php?id=<?php
echo $_REQUEST['id'];
?>&a=94<?php
echo $add_path;
?>";
    }
}

var allowParentSelection = false;
var allowLinkSelection = false;

function enableLinkSelection(b) {
    parent.tree.ca = "link";
    var closed = "<?php
echo $_style["tree_folder"];
?>";
    var opened = "<?php
echo $_style["icons_set_parent"];
?>";
    if (b) {
        document.images["llock"].src = opened;
        allowLinkSelection = true;
    }
    else {
        document.images["llock"].src = closed;
        allowLinkSelection = false;
    }
}

function setLink(lId) {
    if (!allowLinkSelection) {
        window.location.href="index.php?a=3&id="+lId+"<?php
echo $add_path;
?>";
        return;
    }
    else {
        documentDirty=true;
        document.mutate.ta.value=lId;
    }
}

function enableParentSelection(b) {
    parent.tree.ca = "parent";
    var closed = "<?php
echo $_style["tree_folder"];
?>";
    var opened = "<?php
echo $_style["icons_set_parent"];
?>";
    if (b) {
        document.images["plock"].src = opened;
        allowParentSelection = true;
    }
    else {
        document.images["plock"].src = closed;
        allowParentSelection = false;
    }
}

function setParent(pId, pName) {
    if (!allowParentSelection) {
        window.location.href="index.php?a=3&id="+pId+"<?php
echo $add_path;
?>";
        return;
    }
    else {
        if (pId==0 || checkParentChildRelation(pId, pName)) {
            documentDirty=true;
            document.mutate.parent.value=pId;
            var elm = document.getElementById('parentName');
            if (elm) {
                elm.innerHTML = (pId + " (" + pName + ")");
            }
        }
    }
}

// check if the selected parent is a child of this document
function checkParentChildRelation(pId, pName) {
    var sp;
    var id = document.mutate.id.value;
    var tdoc = parent.tree.document;
    var pn = (tdoc.getElementById) ? tdoc.getElementById("node"+pId) : tdoc.all["node"+pId];
    if (!pn) return;
    if (pn.id.substr(4)==id) {
        alert("<?php
echo $_lang['illegal_parent_self'];
?>");
        return;
    }
    else {
        while (pn.getAttribute("p")>0) {
            pId = pn.getAttribute("p");
            pn = (tdoc.getElementById) ? tdoc.getElementById("node"+pId) : tdoc.all["node"+pId];
            if (pn.id.substr(4)==id) {
                alert("<?php
echo $_lang['illegal_parent_child'];
?>");
                return;
            }
        }
    }
    return true;
}

function clearKeywordSelection() {
    var opt = document.mutate.elements["keywords[]"].options;
    for (i = 0; i < opt.length; i++) {
        opt[i].selected = false;
    }
}

function clearMetatagSelection() {
    var opt = document.mutate.elements["metatags[]"].options;
    for (i = 0; i < opt.length; i++) {
        opt[i].selected = false;
    }
}

var curTemplate = -1;
var curTemplateIndex = 0;
function storeCurTemplate() {
    var dropTemplate = document.getElementById('template');
    if (dropTemplate) {
        for (var i=0; i<dropTemplate.length; i++) {
            if (dropTemplate[i].selected) {
                curTemplate = dropTemplate[i].value;
                curTemplateIndex = i;
            }
        }
    }
}
function templateWarning() {
    var dropTemplate = document.getElementById('template');
    if (dropTemplate) {
        for (var i=0; i<dropTemplate.length; i++) {
            if (dropTemplate[i].selected) {
                newTemplate = dropTemplate[i].value;
                break;
            }
        }
    }
    if (curTemplate == newTemplate) {return;}

    if(documentDirty===true) {
        if (confirm('<?php
echo $_lang['tmplvar_change_template_msg'];
?>')) {
            documentDirty=false;
            document.mutate.a.value = <?php
echo $action;
?>;
            document.mutate.newtemplate.value = newTemplate;
            document.mutate.submit();
        } else {
            dropTemplate[curTemplateIndex].selected = true;
        }
    }
    else {
        document.mutate.a.value = <?php
echo $action;
?>;
        document.mutate.newtemplate.value = newTemplate;
        document.mutate.submit();
    }
}

// Added for RTE selection
function changeRTE() {
    var whichEditor = document.getElementById('which_editor');
    if (whichEditor) {
        for (var i = 0; i < whichEditor.length; i++) {
            if (whichEditor[i].selected) {
                newEditor = whichEditor[i].value;
                break;
            }
        }
    }
    var dropTemplate = document.getElementById('template');
    if (dropTemplate) {
        for (var i = 0; i < dropTemplate.length; i++) {
            if (dropTemplate[i].selected) {
                newTemplate = dropTemplate[i].value;
                break;
            }
        }
    }

    documentDirty=false;
    document.mutate.a.value = <?php
echo $action;
?>;
    document.mutate.newtemplate.value = newTemplate;
    document.mutate.which_editor.value = newEditor;
    document.mutate.submit();
}

/**
 * Snippet properties
 */

var snippetParams = {};     // Snippet Params
var currentParams = {};     // Current Params
var lastsp, lastmod = {};

function showParameters(ctrl) {
    var c,p,df,cp;
    var ar,desc,value,key,dt;

    cp = {};
    currentParams = {}; // reset;

    if (ctrl) {
        f = ctrl.form;
    } else {
        f= document.forms['mutate'];
        ctrl = f.snippetlist;
    }

    // get display format
    df = "";//lastsp = ctrl.options[ctrl.selectedIndex].value;

    // load last modified param values
    if (lastmod[df]) cp = lastmod[df].split("&");
    for (p = 0; p < cp.length; p++) {
        cp[p]=(cp[p]+'').replace(/^\s|\s$/,""); // trim
        ar = cp[p].split("=");
        currentParams[ar[0]]=ar[1];
    }

    // setup parameters
    dp = (snippetParams[df]) ? snippetParams[df].split("&"):[""];
    if (dp) {
        t='<table width="100%" class="displayparams"><thead><tr><td width="50%"><?php
echo $_lang['parameter'];
?><\/td><td width="50%"><?php
echo $_lang['value'];
?><\/td><\/tr><\/thead>';
        for (p = 0; p < dp.length; p++) {
            dp[p]=(dp[p]+'').replace(/^\s|\s$/,""); // trim
            ar = dp[p].split("=");
            key = ar[0]     // param
            ar = (ar[1]+'').split(";");
            desc = ar[0];   // description
            dt = ar[1];     // data type
            value = decode((currentParams[key]) ? currentParams[key]:(dt=='list') ? ar[3] : (ar[2])? ar[2]:'');
            if (value!=currentParams[key]) currentParams[key] = value;
            value = (value+'').replace(/^\s|\s$/,""); // trim
            if (dt) {
                switch(dt) {
                    case 'int':
                        c = '<input type="text" name="prop_'+key+'" value="'+value+'" size="30" onchange="setParameter(\''+key+'\',\''+dt+'\',this)" \/>';
                        break;
                    case 'list':
                        c = '<select name="prop_'+key+'" height="1" style="width:168px" onchange="setParameter(\''+key+'\',\''+dt+'\',this)">';
                        ls = (ar[2]+'').split(",");
                        if (currentParams[key]==ar[2]) currentParams[key] = ls[0]; // use first list item as default
                        for (i=0;i<ls.length;i++) {
                            c += '<option value="'+ls[i]+'"'+((ls[i]==value)? ' selected="selected"':'')+'>'+ls[i]+'<\/option>';
                        }
                        c += '<\/select>';
                        break;
                    default:  // string
                        c = '<input type="text" name="prop_'+key+'" value="'+value+'" size="30" onchange="setParameter(\''+key+'\',\''+dt+'\',this)" \/>';
                        break;

                }
                t +='<tr><td bgcolor="#FFFFFF" width="50%">'+desc+'<\/td><td bgcolor="#FFFFFF" width="50%">'+c+'<\/td><\/tr>';
            };
        }
        t+='<\/table>';
        td = (document.getElementById) ? document.getElementById('snippetparams'):document.all['snippetparams'];
        td.innerHTML = t;
    }
    implodeParameters();
}

function setParameter(key,dt,ctrl) {
    var v;
    if (!ctrl) return null;
    switch (dt) {
        case 'int':
            ctrl.value = parseInt(ctrl.value);
            if (isNaN(ctrl.value)) ctrl.value = 0;
            v = ctrl.value;
            break;
        case 'list':
            v = ctrl.options[ctrl.selectedIndex].value;
            break;
        default:
            v = ctrl.value+'';
            break;
    }
    currentParams[key] = v;
    implodeParameters();
}

function resetParameters() {
    document.mutate.params.value = "";
    lastmod[lastsp]="";
    showParameters();
}
// implode parameters
function implodeParameters() {
    var v, p, s = '';
    for (p in currentParams) {
        v = currentParams[p];
        if (v) s += '&'+p+'='+ encode(v);
    }
    //document.forms['mutate'].params.value = s;
    if (lastsp) lastmod[lastsp] = s;
}

function encode(s) {
    s = s+'';
    s = s.replace(/\=/g,'%3D'); // =
    s = s.replace(/\&/g,'%26'); // &
    return s;
}

function decode(s) {
    s = s+'';
    s = s.replace(/\%3D/g,'='); // =
    s = s.replace(/\%26/g,'&'); // &
    return s;
}
/* ]]> */
</script>

<form name="mutate" id="mutate" class="content" method="post" enctype="multipart/form-data" action="index.php">
<?php
// invoke OnDocFormPrerender event
$evtOut = $modx->invokeEvent('OnDocFormPrerender', array(
  'id' => $id
));
if (is_array($evtOut))
  echo implode('', $evtOut);

/*************************/
$dir  = isset($_REQUEST['dir']) ? $_REQUEST['dir'] : '';
$sort = isset($_REQUEST['sort']) ? $_REQUEST['sort'] : 'createdon';
$page = isset($_REQUEST['page']) ? (int) $_REQUEST['page'] : '';
/*************************/

?>
<input type="hidden" name="a" value="5" />
<input type="hidden" name="id" value="<?php
echo $content['id'];
?>" />
<input type="hidden" name="mode" value="<?php
echo (int) $_REQUEST['a'];
?>" />
<input type="hidden" name="MAX_FILE_SIZE" value="<?php
echo isset($upload_maxsize) ? $upload_maxsize : 1048576;
?>" />
<input type="hidden" name="refresh_preview" value="0" />
<input type="hidden" name="newtemplate" value="" />
<input type="hidden" name="dir" value="<?php
echo $dir;
?>" />
<input type="hidden" name="sort" value="<?php
echo $sort;
?>" />
<input type="hidden" name="page" value="<?php
echo $page;
?>" />

<fieldset id="create_edit">
    <h1><?php
if ($_REQUEST['id']) {
  echo $_lang['edit_resource_title'] . ' <small>(' . $_REQUEST['id'] . ')</small>';
} else {
  echo $_lang['create_resource_title'];
}
?></h1>

<div id="actions">
      <ul class="actionButtons">
          <li id="Button1">
            <a href="#" class="primary" onClick="documentDirty=false; document.mutate.save.click();">
              <img alt="icons_save" src="<?php
echo $_style["icons_save"];
?>" /> <?php
echo $_lang['save'];
?>
            </a><span class="plus"> + </span>
            <select id="stay" name="stay">
              <?php
if ($modx->hasPermission('new_document')) {
?>
              <option id="stay1" value="1" <?php
  echo $_REQUEST['stay'] == '1' ? ' selected="selected"' : '';
?> ><?php
  echo $_lang['stay_new'];
?></option>
              <?php
}
?>
              <option id="stay2" value="2" <?php
echo $_REQUEST['stay'] == '2' ? ' selected="selected"' : '';
?> ><?php
echo $_lang['stay'];
?></option>
              <option id="stay3" value=""  <?php
echo $_REQUEST['stay'] == '' ? ' selected="selected"' : '';
?>  ><?php
echo $_lang['close'];
?></option>
            </select>
          </li>
          <?php
if ($_REQUEST['a'] == '4' || $_REQUEST['a'] == '72') {
?>
          <li id="Button2" class="disabled"><a href="#" onClick="deletedocument();"><img src="<?php
  echo $_style["icons_delete_document"];
?>" alt="icons_delete_document" /> <?php
  echo $_lang['delete'];
?></a></li>
          <?php
} else {
?>
          <li id="Button6"><a href="#" onClick="duplicatedocument();"><img src="<?php
  echo $_style["icons_resource_duplicate"];
?>" alt="icons_resource_duplicate" /> <?php
  echo $_lang['duplicate'];
?></a></li>
          <li id="Button3"><a href="#" onClick="deletedocument();"><img src="<?php
  echo $_style["icons_delete_document"];
?>" alt="icons_delete_document" /> <?php
  echo $_lang['delete'];
?></a></li>
          <?php
}
?>
          <li id="Button4"><a href="#" onClick="documentDirty=false;<?php
echo $id == 0 ? "document.location.href='index.php?a=2';" : "document.location.href='index.php?a=3&amp;id=$id" . htmlspecialchars($add_path) . "';";
?>"><img alt="icons_cancel" src="<?php
echo $_style["icons_cancel"];
?>" /> <?php
echo $_lang['cancel'];
?></a></li>
          <li id="Button5"><a href="#" onClick="window.open('<?php
echo $modx->makeUrl($id);
?>','previeWin');"><img alt="icons_preview_resource" src="<?php
echo $_style["icons_preview_resource"];
?>" /> <?php
echo $_lang['preview'];
?></a></li>
      </ul>
</div>

<!-- start main wrapper -->
<div class="sectionBody">
<script type="text/javascript" src="media/script/tabpane.js"></script>

<div class="tab-pane" id="documentPane">
    <script type="text/javascript">
    tpSettings = new WebFXTabPane( document.getElementById( "documentPane" ), <?php
echo $modx->config['remember_last_tab'] == 1 ? 'true' : 'false';
?> );
    </script>

<?php
$mutate_content_fields = array();
//$modx->db->update(array('template_fields' => mysql_real_escape_string(serialize($mutate_content_fields))), $tbl_site_templates, "id={$content['template']}");
//$rs = $modx->db->select("template_fields", $tbl_site_templates, "id='". $content['template'] ."'");
//$mutate_content_fields = unserialize($modx->db->getValue($rs));
//print_r($mutate_content_fields);
//if(!$mutate_content_fields) {
$mutate_content_fields = array(
  'General' => array(
    'title' => $_lang['settings_general'],
    'fields' => array(
      'pagetitle' => array(
        'field' => array(
          'title' => $_lang['resource_title'],
          'help' => $_lang['resource_title_help']
        )
      ),
      'longtitle' => array(
        'field' => array(
          'title' => $_lang['long_title'],
          'help' => $_lang['resource_long_title_help']
        )
      ),
      'description' => array(
        'field' => array(
          'title' => $_lang['resource_description'],
          'help' => $_lang['resource_description_help']
        )
      ),
      'alias' => array(
        'field' => array(
          'title' => addslashes($_lang['resource_alias']),
          'help' => addslashes($_lang['resource_alias_help'])
        )
      ),
      'link_attributes' => array(
        'field' => array(
          'title' => addslashes($_lang['link_attributes']),
          'help' => addslashes($_lang['link_attributes_help'])
        )
      ),
      'weblink' => array(
        'field' => array(
          'title' => $_lang['weblink'],
          'help' => $_lang['resource_weblink_help']
        )
      ),
      'introtext' => array(
        'field' => array(
          'title' => $_lang['resource_summary'],
          'help' => $_lang['resource_summary_help']
        )
      ),
      'template' => array(
        'field' => array(
          'title' => $_lang['page_data_template'],
          'help' => $_lang['page_data_template_help']
        )
      ),
      'menutitle' => array(
        'field' => array(
          'title' => $_lang['resource_opt_menu_title'],
          'help' => $_lang['resource_opt_menu_title_help']
        )
      ),
      'menuindex' => array(
        'field' => array(
          'title' => $_lang['resource_opt_menu_index']
        )
      ),
      'parent' => array(
        'field' => array(
          'title' => $_lang['resource_parent'],
          'help' => $_lang['resource_parent_help']
        )
      )
    ),
    'roles' => ''
  ),
  'Settings' => array(
    'title' => $_lang['settings_page_settings'],
    'fields' => array(
      'published' => array(
        'field' => array(
          'title' => $_lang['resource_opt_published'],
          'help' => $_lang['resource_opt_published_help']
        )
      ),
      'pub_date' => array(
        'field' => array(
          'title' => $_lang['page_data_publishdate'],
          'help' => $_lang['page_data_publishdate_help']
        )
      ),
      'unpub_date' => array(
        'field' => array(
          'title' => $_lang['page_data_unpublishdate'],
          'help' => $_lang['page_data_unpublishdate_help']
        )
      ),
      'type' => array(
        'field' => array(
          'title' => $_lang['resource_type'],
          'help' => $_lang['resource_type_message']
        )
      ),
      'contentType' => array(
        'field' => array(
          'title' => $_lang['page_data_contentType'],
          'help' => $_lang['page_data_contentType_help']
        )
      ),
      'content_dispo' => array(
        'field' => array(
          'title' => $_lang['resource_opt_contentdispo'],
          'help' => $_lang['page_data_contentType_help']
        )
      ),
      'alias_visible' => array(
        'field' => array(
          'title' => $_lang['resource_opt_alvisibled'],
          'help' => addslashes($_lang['resource_opt_contentdispo_help'])
        )
      ),
      'isfolder' => array(
        'field' => array(
          'title' => $_lang['resource_opt_folder'],
          'help' => $_lang['resource_opt_folder_help']
        )
      ),
      'richtext' => array(
        'field' => array(
          'title' => $_lang['resource_opt_richtext'],
          'help' => $_lang['resource_opt_richtext_help']
        )
      ),
      'donthit' => array(
        'field' => array(
          'title' => $_lang['track_visitors_title'],
          'help' => $_lang['resource_opt_trackvisit_help']
        )
      ),
      'searchable' => array(
        'field' => array(
          'title' => $_lang['page_data_searchable'],
          'help' => $_lang['page_data_searchable_help']
        )
      ),
      'cacheable' => array(
        'field' => array(
          'title' => $_lang['page_data_cacheable'],
          'help' => $_lang['page_data_cacheable_help']
        )
      ),
      'syncsite' => array(
        'field' => array(
          'title' => $_lang['resource_opt_emptycache'],
          'help' => $_lang['resource_opt_emptycache_help']
        )
      )
    ),
    'roles' => ''
  ),
  'Custom' => array(
    'title' => 'Кастом',
    'fields' => array(
      'tv3' => array(
        'tv' => array()
      ),
      'tv4' => array(
        'tv' => array()
      ),
      'tv1' => array(
        'tv' => array()
      ),
      'tv7' => array(
        'tv' => array()
      )
    )
  ),
  'content' => array(
    'title' => 'Подробное описание',
    'fields' => array(
      'content' => array(
        'field' => array(
          'title' => $_lang['which_editor_title']
        )
      )
    ),
    'roles' => ''
  )
);
//}

?>

<?php
// Variables		
if (($content['type'] == 'document' || $_REQUEST['a'] == '4') || ($content['type'] == 'reference' || $_REQUEST['a'] == 72)) {
  $template = $default_template;
  if (isset($_REQUEST['newtemplate'])) {
    $template = $_REQUEST['newtemplate'];
  } else {
    if (isset($content['template']))
      $template = $content['template'];
  }
  $rs    = $modx->db->select("
				DISTINCT tv.*, IF(tvc.value!='',tvc.value,tv.default_text) as value", "{$tbl_site_tmplvars} AS tv
					 INNER JOIN {$tbl_site_tmplvar_templates} AS tvtpl ON tvtpl.tmplvarid = tv.id
					 LEFT JOIN {$tbl_site_tmplvar_contentvalues} AS tvc ON tvc.tmplvarid=tv.id AND tvc.contentid='{$id}'
					 LEFT JOIN {$tbl_site_tmplvar_access} AS tva ON tva.tmplvarid=tv.id", "tvtpl.templateid='{$template}' AND (1='{$_SESSION['mgrRole']}' OR ISNULL(tva.documentgroup)" . (!$docgrp ? '' : " OR tva.documentgroup IN ({$docgrp})") . ")", 'tvtpl.rank,tv.rank, tv.id');
  $limit = $modx->db->getRecordCount($rs);
  if ($limit > 0) {
    require_once(MODX_MANAGER_PATH . 'includes/tmplvars.inc.php');
    require_once(MODX_MANAGER_PATH . 'includes/tmplvars.commands.inc.php');
    $i = 0;
    while ($row = $modx->db->getRow($rs)) {
      // Go through and display all Template Variables
      if ($row['type'] == 'richtext' || $row['type'] == 'htmlarea') {
        // Add richtext editor to the list
        if (is_array($replace_richtexteditor)) {
          $replace_richtexteditor = array_merge($replace_richtexteditor, array(
            "tv" . $row['id']
          ));
        } else {
          $replace_richtexteditor = array(
            "tv" . $row['id']
          );
        }
      }
      foreach ($mutate_content_fields as $k => $v) {
        if (isset($v['fields']['tv' . $row['id']])) {
          $mutate_content_fields[$k]['fields']['tv' . $row['id']] = array(
            'tv' => $row
          );
          unset($row);
        }
      }
      if ($row['id']) {
        $mutate_content_fields['General']['fields']['tv' . $row['id']] = array(
          'tv' => $row
        );
      }
    }
  }
}
// end Variables				


$mx_can_pub = $modx->hasPermission('publish_document') ? '' : 'disabled="disabled" ';

foreach ($mutate_content_fields as $tabName => $tab) {
  if ($tab['title']) {
    $field       = '';
    $title_width = 150;
    foreach ($tab['fields'] as $fieldName => $data) {
      // Start Render content fields ($data)
      if ($fieldName) {
        $title = '<span class="warning">' . $data['field']['title'] . '</span>';
        $help  = $data['field']['help'] ? ' <img src="' . $_style["icons_tooltip_over"] . '" alt="' . stripcslashes($data['field']['help']) . '" style="cursor:help;" />' : '';
        switch ($fieldName) {
          case 'pagetitle':
          case 'longtitle':
          case 'description':
          case 'alias':
          case 'link_attributes':
          case 'menutitle':
            $field .= '<tr>';
            $field .= '<td width="' . $title_width . '">' . $title . '</td>
							<td><input name="' . $fieldName . '" type="text" maxlength="255" value="' . $modx->htmlspecialchars(stripslashes($content[$fieldName])) . '" class="inputBox" onChange="documentDirty=true;" spellcheck="true" />
							' . $help . '</td>
							</tr>';
            break;
          
          case 'weblink':
            if ($content['type'] == 'reference' || $_REQUEST['a'] == '72') {
              $field .= '<tr>
							<td>' . $title . ' <img name="llock" src="' . $_style["tree_folder"] . '" alt="tree_folder" onClick="enableLinkSelection(!allowLinkSelection);" style="cursor:pointer;" /></td>
							<td><input name="ta" type="text" maxlength="255" value="' . (!empty($content['content']) ? stripslashes($content['content']) : "http://") . '" class="inputBox" onChange="documentDirty=true;" />
							' . $help . '
							</td></tr>';
            }
            break;
          
          case 'introtext':
            $field .= '<tr>
						<td valign="top" width="' . $title_width . '">' . $title . '</td>
						<td valign="top"><textarea name="introtext" class="inputBox" rows="3" cols="" onChange="documentDirty=true;">' . $modx->htmlspecialchars(stripslashes($content['introtext'])) . '</textarea>
						<img src="' . $_style["icons_tooltip_over"] . '" alt="' . $_lang['resource_summary_help'] . '" style="cursor:help;" spellcheck="true"/>
						</td></tr>';
            break;
          
          case 'template':
            $field .= '<tr>
						<td>' . $title . '</td>
						<td><select id="template" name="template" class="inputBox" onChange="templateWarning();" style="width:308px">
						<option value="0">(blank)</option>';
            $rs              = $modx->db->select("t.templatename, t.id, c.category", $modx->getFullTableName('site_templates') . " AS t LEFT JOIN " . $modx->getFullTableName('categories') . " AS c ON t.category = c.id", '', 'c.category, t.templatename ASC');
            $currentCategory = '';
            while ($row = $modx->db->getRow($rs)) {
              $thisCategory = $row['category'];
              if ($thisCategory == null) {
                $thisCategory = $_lang["no_category"];
              }
              if ($thisCategory != $currentCategory) {
                if ($closeOptGroup) {
                  $field .= "</optgroup>";
                }
                $field .= "<optgroup label=\"$thisCategory\">";
                $closeOptGroup = true;
              }
              if (isset($_REQUEST['newtemplate'])) {
                $selectedtext = $row['id'] == $_REQUEST['newtemplate'] ? ' selected="selected"' : '';
              } else {
                if (isset($content['template'])) {
                  $selectedtext = $row['id'] == $content['template'] ? ' selected="selected"' : '';
                } else {
                  $default_template    = getDefaultTemplate();
                  $selectedtext        = $row['id'] == $default_template ? ' selected="selected"' : '';
                  $content['template'] = $default_template;
                }
              }
              $field .= '<option value="' . $row['id'] . '"' . $selectedtext . '>' . $row['templatename'] . "</option>";
              $currentCategory = $thisCategory;
            }
            if ($thisCategory != '') {
              $field .= "</optgroup>";
            }
            $field .= '	
						</select>
						' . $help . '
						</td></tr>';
            break;
          
          case 'menuindex':
            $field .= '<tr>
						<td width="' . $title_width . '">' . $title . '</td>
						<td><input name="menuindex" type="text" maxlength="6" value="' . $content['menuindex'] . '" class="inputBox" style="width:30px;" onChange="documentDirty=true;" />
							<input type="button" value="&lt;" onClick="var elm = document.mutate.menuindex;var v=parseInt(elm.value+\'\')-1;elm.value=v>0? v:0;elm.focus();documentDirty=true;" />
							<input type="button" value="&gt;" onClick="var elm = document.mutate.menuindex;var v=parseInt(elm.value+\'\')+1;elm.value=v>0? v:0;elm.focus();documentDirty=true;" />
							<img src="' . $_style["icons_tooltip_over"] . '" alt="' . $_lang['resource_opt_menu_index_help'] . '" style="cursor:help;" />
							<span class="warning">' . $_lang['resource_opt_show_menu'] . '</span>
							<input name="hidemenucheck" type="checkbox" class="checkbox" ' . ($content['hidemenu'] != 1 ? 'checked="checked"' : '') . ' onClick="changestate(document.mutate.hidemenu);" />
							<input type="hidden" name="hidemenu" class="hidden" value="' . ($content['hidemenu'] == 1 ? 1 : 0) . '" />
							<img src="' . $_style["icons_tooltip_over"] . '" alt="' . $_lang['resource_opt_show_menu_help'] . '" style="cursor:help;" />
						</td></tr>';
            break;
          
          case 'parent':
            $field .= '<tr>
						<td valign="top">' . $title . '</td>
						<td valign="top">';
            $parentlookup = false;
            if (isset($_REQUEST['id'])) {
              if ($content['parent'] == 0) {
                $parentname = $site_name;
              } else {
                $parentlookup = $content['parent'];
              }
            } elseif (isset($_REQUEST['pid'])) {
              if ($_REQUEST['pid'] == 0) {
                $parentname = $site_name;
              } else {
                $parentlookup = $_REQUEST['pid'];
              }
            } elseif (isset($_POST['parent'])) {
              if ($_POST['parent'] == 0) {
                $parentname = $site_name;
              } else {
                $parentlookup = $_POST['parent'];
              }
            } else {
              $parentname        = $site_name;
              $content['parent'] = 0;
            }
            if ($parentlookup !== false && is_numeric($parentlookup)) {
              $rs         = $modx->db->select('pagetitle', $modx->getFullTableName('site_content'), "id='{$parentlookup}'");
              $parentname = $modx->db->getValue($rs);
              if (!$parentname) {
                $modx->webAlertAndQuit($_lang["error_no_parent"]);
              }
            }
            $field .= '
								<img alt="tree_folder" name="plock" src="' . $_style["tree_folder"] . '" onClick="enableParentSelection(!allowParentSelection);" style="cursor:pointer;" />
								<b><span id="parentName">' . (isset($_REQUEST['pid']) ? $_REQUEST['pid'] : $content['parent']) . ' (' . $parentname . ')</span></b>
								' . $help . '
								<input type="hidden" name="parent" value="' . (isset($_REQUEST['pid']) ? $_REQUEST['pid'] : $content['parent']) . '" onChange="documentDirty=true;" />
							</td></tr>';
            break;
          
          case 'content':
            if ($content['type'] == 'document' || $_REQUEST['a'] == '4') {
              $field .= '<tr><td colspan="2">';
              if (($content['richtext'] == 1 || $_REQUEST['a'] == '4') && $use_editor == 1) {
                $field .= '<textarea id="ta" name="ta" cols="" rows="" style="width:100%; height: 400px;" onChange="documentDirty=true;">' . $modx->htmlspecialchars($content['content']) . '</textarea>
												<span class="warning">' . $_lang['which_editor_title'] . '</span>
												<select id="which_editor" name="which_editor" onChange="changeRTE();">
											<option value="none">' . $_lang['none'] . '</option>';
                $evtOut = $modx->invokeEvent("OnRichTextEditorRegister");
                if (is_array($evtOut)) {
                  for ($i = 0; $i < count($evtOut); $i++) {
                    $editor = $evtOut[$i];
                    $field .= '<option value="' . $editor . '"' . ($which_editor == $editor ? ' selected="selected"' : '') . '>' . $editor . "</option>";
                  }
                }
                $field .= '</select>';
                if (is_array($replace_richtexteditor)) {
                  $replace_richtexteditor = array_merge($replace_richtexteditor, array(
                    'ta'
                  ));
                } else {
                  $replace_richtexteditor = array(
                    'ta'
                  );
                }
              } else {
                $field .= '<div style="width:100%"><textarea class="phptextarea" id="ta" name="ta" style="width:100%; height: 400px;" onchange="documentDirty=true;">' . $modx->htmlspecialchars($content['content']) . '</textarea></div>';
              }
              $field .= '</td></tr>';
            }
            break;
          
          case 'published':
            $field .= '<tr>
											<td width="' . $title_width . '">' . $title . '</td>
											<td><input ' . $mx_can_pub . 'name="publishedcheck" type="checkbox" class="checkbox" ' . ((isset($content['published']) && $content['published'] == 1) || (!isset($content['published']) && $publish_default == 1) ? "checked" : '') . ' onClick="changestate(document.mutate.published);" />
											<input type="hidden" name="published" value="' . ((isset($content['published']) && $content['published'] == 1) || (!isset($content['published']) && $publish_default == 1) ? 1 : 0) . '" />
											' . $help . '</td>
									</tr>';
            break;
          
          case 'pub_date':
          case 'unpub_date':
            $field .= '<tr>
											<td>' . $title . '</td>
											<td><input id="' . $fieldName . '" ' . $mx_can_pub . 'name="' . $fieldName . '" class="DatePicker" value="' . ($content[$fieldName] == "0" || !isset($content[$fieldName]) ? '' : $modx->toDateFormat($content[$fieldName])) . '" onBlur="documentDirty=true;" />
											<a href="javascript:void(0);" onClick="javascript:document.mutate.' . $fieldName . '.value=\'\'; return true;" onMouseOver="window.status=\'' . $_lang['remove_date'] . '\'; return true;" onMouseOut="window.status=\'\'; return true;" style="cursor:pointer; cursor:hand;">
											<img src="' . $_style["icons_cal_nodate"] . '" width="16" height="16" border="0" alt="' . $_lang['remove_date'] . '" /></a>
											' . $help . '
											</td>
									</tr>
									<tr>
											<td></td>
											<td style="color: #555;font-size:10px"><em>' . $modx->config['datetime_format'] . ' HH:MM:SS</em></td>
									</tr>';
            break;
          
          case 'richtext':
          case 'donthit':
          case 'searchable':
          case 'cacheable':
          case 'syncsite':
          case 'alias_visible':
          case 'isfolder':
            if ($fieldName == 'richtext') {
              $checked = $content['richtext'] == 0 && $_REQUEST['a'] == '27' ? '' : "checked";
              $value   = $content['richtext'] == 0 && $_REQUEST['a'] == '27' ? 0 : 1;
            } elseif ($fieldName == 'donthit') {
              $checked = ($content['donthit'] != 1) ? 'checked="checked"' : '';
              $value   = ($content['donthit'] == 1) ? 1 : 0;
            } elseif ($fieldName == 'searchable') {
              $checked = (isset($content['searchable']) && $content['searchable'] == 1) || (!isset($content['searchable']) && $search_default == 1) ? "checked" : '';
              $value   = (isset($content['searchable']) && $content['searchable'] == 1) || (!isset($content['searchable']) && $search_default == 1) ? 1 : 0;
            } elseif ($fieldName == 'cacheable') {
              $checked = (isset($content['cacheable']) && $content['cacheable'] == 1) || (!isset($content['cacheable']) && $cache_default == 1) ? "checked" : '';
              $value   = (isset($content['cacheable']) && $content['cacheable'] == 1) || (!isset($content['cacheable']) && $cache_default == 1) ? 1 : 0;
            } elseif ($fieldName == 'syncsite') {
              $checked = 'checked="checked"';
              $value   = '1';
            } elseif ($fieldName == 'alias_visible') {
              $checked = (!isset($content['alias_visible']) || $content['alias_visible'] == 1) ? "checked" : '';
              $value   = (!isset($content['alias_visible']) || $content['alias_visible'] == 1) ? 1 : 0;
            } elseif ($fieldName == 'isfolder') {
              $checked = ($content['isfolder'] == 1 || $_REQUEST['a'] == '85') ? "checked" : '';
              $value   = ($content['isfolder'] == 1 || $_REQUEST['a'] == '85') ? 1 : 0;
            } else {
              $checked = ($content[$fieldName] == 1) ? "checked" : '';
              $value   = ($content[$fieldName] == 1) ? 1 : 0;
            }
            $field .= '<tr>
											<td width="' . $title_width . '">' . $title . '</td>
											<td><input name="' . $fieldName . 'check" type="checkbox" class="checkbox" ' . $checked . ' onClick="changestate(document.mutate.' . $fieldName . ');" />
											<input type="hidden" name="' . $fieldName . '" value="' . $value . '" onChange="documentDirty=true;" />
											' . $help . '</td>
									</tr>';
            break;
          
          case 'type':
            if ($_SESSION['mgrRole'] == 1 || $_REQUEST['a'] != '27' || $_SESSION['mgrInternalKey'] == $content['createdby']) {
              $field .= '<tr>
									<td width="' . $title_width . '">' . $title . '</td>
									<td><select name="type" class="inputBox" onChange="documentDirty=true;" style="width:200px">
											<option value="document"' . (($content['type'] == "document" || $_REQUEST['a'] == '85' || $_REQUEST['a'] == '4') ? ' selected="selected"' : "") . '>' . $_lang["resource_type_webpage"] . '</option>
											<option value="reference"' . (($content['type'] == "reference" || $_REQUEST['a'] == '72') ? ' selected="selected"' : "") . '>' . $_lang["resource_type_weblink"] . '</option>
											</select>
											' . $help . '</td>
									</tr>';
            } else {
              if ($content['type'] != 'reference' && $_REQUEST['a'] != '72') {
                $field .= '<input type="hidden" name="type" value="document" />';
              } else {
                $field .= '<input type="hidden" name="type" value="reference" />';
              }
            }
            break;
          
          case 'contentType':
            if ($_SESSION['mgrRole'] == 1 || $_REQUEST['a'] != '27' || $_SESSION['mgrInternalKey'] == $content['createdby']) {
              $field .= '<tr>
									<td width="' . $title_width . '">' . $title . '</td>
									<td><select name="contentType" class="inputBox" onChange="documentDirty=true;" style="width:200px">';
              if (!$content['contentType'])
                $content['contentType'] = 'text/html';
              $custom_contenttype = (isset($custom_contenttype) ? $custom_contenttype : "text/html,text/plain,text/xml");
              $ct                 = explode(",", $custom_contenttype);
              for ($i = 0; $i < count($ct); $i++) {
                $field .= '<option value="' . $ct[$i] . '"' . ($content['contentType'] == $ct[$i] ? ' selected="selected"' : '') . '>' . $ct[$i] . "</option>";
              }
              $field .= '
											</select>
											' . $help . '</td>
									</tr>';
            } else {
              if ($content['type'] != 'reference' && $_REQUEST['a'] != '72') {
                $field .= '<input type="hidden" name="contentType" value="' . (isset($content['contentType']) ? $content['contentType'] : "text/html") . '" />';
              } else {
                $field .= '<input type="hidden" name="contentType" value="text/html" />';
              }
            }
            break;
          
          case 'content_dispo':
            if ($_SESSION['mgrRole'] == 1 || $_REQUEST['a'] != '27' || $_SESSION['mgrInternalKey'] == $content['createdby']) {
              $field .= '<tr>
											<td width="' . $title_width . '">' . $title . '</td>
											<td><select name="content_dispo" size="1" onChange="documentDirty=true;" style="width:200px">
													<option value="0"' . (!$content['content_dispo'] ? ' selected="selected"' : '') . '>' . $_lang['inline'] . '</option>
													<option value="1"' . ($content['content_dispo'] == 1 ? ' selected="selected"' : '') . '>' . $_lang['attachment'] . '</option>
											</select>
											' . $help . '</td>
									 </tr>';
            } else {
              if ($content['type'] != 'reference' && $_REQUEST['a'] != '72') {
                $field .= '<input type="hidden" name="content_dispo" value="' . (isset($content['content_dispo']) ? $content['content_dispo'] : '0') . '" />';
              }
            }
            break;
          
          default:
            if ($data['tv']) {
              if (array_key_exists('tv' . $data['tv']['id'], $_POST)) {
                if ($data['tv']['type'] == 'listbox-multiple') {
                  $tvPBV = implode('||', $_POST['tv' . $data['tv']['id']]);
                } else {
                  $tvPBV = $_POST['tv' . $data['tv']['id']];
                }
              } else {
                $tvPBV = $data['tv']['value'];
              }
              $tvDescription = (!empty($data['tv']['description'])) ? '<br /><span class="comment">' . $data['tv']['description'] . '</span>' : '';
              $tvInherited   = (substr($tvPBV, 0, 8) == '@INHERIT') ? '<br /><span class="comment inherited">(' . $_lang['tmplvars_inherited'] . ')</span>' : '';
              $field .= '<tr><td valign="top" width="' . $title_width . '"><span class="warning">' . $data['tv']['caption'] . "</span>" . $tvDescription . $tvInherited . '</td><td valign="top" style="position:relative;">' . renderFormElement($data['tv']['type'], $data['tv']['id'], $data['tv']['default_text'], $data['tv']['elements'], $tvPBV, '', $data['tv']) . '</td></tr>';
            }
            break;
        }
      }
      // End Render content fields
    }
    
    if ($field) {
      echo '<!-- ' . $tabName . ' -->
			<div class="tab-page" id="tab' . $tabName . '">
			<h2 class="tab">' . $tab['title'] . '</h2>
			<script type="text/javascript">tpSettings.addTabPage(document.getElementById("tab' . $tabName . '"));</script>
			<table width="100%" border="0" cellspacing="0" cellpadding="0">' . $field . '</table>
			</div><!-- end #tab' . $tabName . ' -->';
    }
  }
}
unset($mutate_content_fields, $field, $data);


if ($modx->hasPermission('edit_doc_metatags') && $modx->config['show_meta']) {
  // get list of site keywords
  $keywords = array();
  $ds       = $modx->db->select('id, keyword', $tbl_site_keywords, '', 'keyword ASC');
  while ($row = $modx->db->getRow($ds)) {
    $keywords[$row['id']] = $row['keyword'];
  }
  // get selected keywords using document's id
  if (isset($content['id']) && count($keywords) > 0) {
    $keywords_selected = array();
    $ds                = $modx->db->select('keyword_id', $tbl_keyword_xref, "content_id='{$content['id']}'");
    while ($row = $modx->db->getRow($ds)) {
      $keywords_selected[$row['keyword_id']] = ' selected="selected"';
    }
  }
  
  // get list of site META tags
  $metatags = array();
  $ds       = $modx->db->select('id, name', $tbl_site_metatags);
  while ($row = $modx->db->getRow($ds)) {
    $metatags[$row['id']] = $row['name'];
  }
  // get selected META tags using document's id
  if (isset($content['id']) && count($metatags) > 0) {
    $metatags_selected = array();
    $ds                = $modx->db->select('metatag_id', $tbl_site_content_metatags, "content_id='{$content['id']}'");
    while ($row = $modx->db->getRow($ds)) {
      $metatags_selected[$row['metatag_id']] = ' selected="selected"';
    }
  }
?>
    <!-- META Keywords -->
    <div class="tab-page" id="tabMeta">
        <h2 class="tab"><?php
  echo $_lang['meta_keywords'];
?></h2>
        <script type="text/javascript">tpSettings.addTabPage( document.getElementById( "tabMeta" ) );</script>

        <table width="99%" border="0" cellspacing="5" cellpadding="0">
        <tr style="height: 24px;"><td><?php
  echo $_lang['resource_metatag_help'];
?><br /><br />
            <table border="0" style="width:inherit;"><tr>
            <td><span class="warning"><?php
  echo $_lang['keywords'];
?></span><br />
                <select name="keywords[]" multiple="multiple" size="16" class="inputBox" style="width: 200px;" onChange="documentDirty=true;">
                <?php
  foreach ($keywords as $key => $value) {
    $selected = $keywords_selected[$key];
    echo "\t\t\t\t" . '<option value="' . $key . '"' . $selected . '>' . $value . "</option>\n";
  }
?>
                </select>
                <br />
                <input type="button" value="<?php
  echo $_lang['deselect_keywords'];
?>" onClick="clearKeywordSelection();" />
            </td>
            <td><span class="warning"><?php
  echo $_lang['metatags'];
?></span><br />
                <select name="metatags[]" multiple="multiple" size="16" class="inputBox" style="width: 220px;" onChange="documentDirty=true;">
                <?php
  foreach ($metatags as $key => $value) {
    $selected = $metatags_selected[$key];
    echo "\t\t\t\t" . '<option value="' . $key . '"' . $selected . '>' . $value . "</option>\n";
  }
?>
                </select>
                <br />
                <input type="button" value="<?php
  echo $_lang['deselect_metatags'];
?>" onClick="clearMetatagSelection();" />
            </td>
            </table>
            </td>
        </tr>
        </table>
    </div><!-- end #tabMeta -->
<?php
}

/*******************************
 * Document Access Permissions */
if ($use_udperms == 1) {
  $groupsarray = array();
  $sql         = '';
  
  $documentId = ($_REQUEST['a'] == '27' ? $id : (!empty($_REQUEST['pid']) ? $_REQUEST['pid'] : $content['parent']));
  if ($documentId > 0) {
    // Load up, the permissions from the parent (if new document) or existing document
    $rs = $modx->db->select('id, document_group', $tbl_document_groups, "document='{$documentId}'");
    while ($currentgroup = $modx->db->getRow($rs))
      $groupsarray[] = $currentgroup['document_group'] . ',' . $currentgroup['id'];
    
    // Load up the current permissions and names
    $rs = $modx->db->select('dgn.*, groups.id AS link_id', "{$tbl_document_group_names} AS dgn
			LEFT JOIN {$tbl_document_groups} AS groups ON groups.document_group = dgn.id  AND groups.document = '{$documentId}'", '', 'name');
  } else {
    // Just load up the names, we're starting clean
    $rs = $modx->db->select('*, NULL AS link_id', $tbl_document_group_names, '', 'name');
  }
  
  // retain selected doc groups between post
  if (isset($_POST['docgroups']))
    $groupsarray = array_merge($groupsarray, $_POST['docgroups']);
  
  $isManager = $modx->hasPermission('access_permissions');
  $isWeb     = $modx->hasPermission('web_access_permissions');
  
  // Setup Basic attributes for each Input box
  $inputAttributes = array(
    'type' => 'checkbox',
    'class' => 'checkbox',
    'name' => 'docgroups[]',
    'onclick' => 'makePublic(false);'
  );
  $permissions     = array(); // New Permissions array list (this contains the HTML)
  $permissions_yes = 0; // count permissions the current mgr user has
  $permissions_no  = 0; // count permissions the current mgr user doesn't have
  
  // Loop through the permissions list
  while ($row = $modx->db->getRow($rs)) {
    
    // Create an inputValue pair (group ID and group link (if it exists))
    $inputValue = $row['id'] . ',' . ($row['link_id'] ? $row['link_id'] : 'new');
    $inputId    = 'group-' . $row['id'];
    
    $checked = in_array($inputValue, $groupsarray);
    if ($checked)
      $notPublic = true; // Mark as private access (either web or manager)
    
    // Skip the access permission if the user doesn't have access...
    if ((!$isManager && $row['private_memgroup'] == '1') || (!$isWeb && $row['private_webgroup'] == '1'))
      continue;
    
    // Setup attributes for this Input box
    $inputAttributes['id']    = $inputId;
    $inputAttributes['value'] = $inputValue;
    if ($checked)
      $inputAttributes['checked'] = 'checked';
    else
      unset($inputAttributes['checked']);
    
    // Create attribute string list
    $inputString = array();
    foreach ($inputAttributes as $k => $v)
      $inputString[] = $k . '="' . $v . '"';
    
    // Make the <input> HTML
    $inputHTML = '<input ' . implode(' ', $inputString) . ' />';
    
    // does user have this permission?
    $rsp   = $modx->db->select('COUNT(mg.id)', "{$tbl_membergroup_access} AS mga, {$tbl_member_groups} AS mg", "mga.membergroup = mg.user_group AND mga.documentgroup = {$row['id']} AND mg.member = {$_SESSION['mgrInternalKey']}");
    $count = $modx->db->getValue($rsp);
    if ($count > 0) {
      ++$permissions_yes;
    } else {
      ++$permissions_no;
    }
    $permissions[] = "\t\t" . '<li>' . $inputHTML . '<label for="' . $inputId . '">' . $row['name'] . '</label></li>';
  }
  // if mgr user doesn't have access to any of the displayable permissions, forget about them and make doc public
  if ($_SESSION['mgrRole'] != 1 && ($permissions_yes == 0 && $permissions_no > 0)) {
    $permissions = array();
  }
  
  // See if the Access Permissions section is worth displaying...
  if (!empty($permissions)) {
    // Add the "All Document Groups" item if we have rights in both contexts
    if ($isManager && $isWeb)
      array_unshift($permissions, "\t\t" . '<li><input type="checkbox" class="checkbox" name="chkalldocs" id="groupall"' . (!$notPublic ? ' checked="checked"' : '') . ' onclick="makePublic(true);" /><label for="groupall" class="warning">' . $_lang['all_doc_groups'] . '</label></li>');
    // Output the permissions list...
?>
<!-- Access Permissions -->
<div class="tab-page" id="tabAccess">
    <h2 class="tab" id="tab_access_header"><?php
    echo $_lang['access_permissions'];
?></h2>
    <script type="text/javascript">tpSettings.addTabPage( document.getElementById( "tabAccess" ) );</script>
    <script type="text/javascript">
        /* <![CDATA[ */
        function makePublic(b) {
            var notPublic = false;
            var f = document.forms['mutate'];
            var chkpub = f['chkalldocs'];
            var chks = f['docgroups[]'];
            if (!chks && chkpub) {
                chkpub.checked=true;
                return false;
            } else if (!b && chkpub) {
                if (!chks.length) notPublic = chks.checked;
                else for (i = 0; i < chks.length; i++) if (chks[i].checked) notPublic = true;
                chkpub.checked = !notPublic;
            } else {
                if (!chks.length) chks.checked = (b) ? false : chks.checked;
                else for (i = 0; i < chks.length; i++) if (b) chks[i].checked = false;
                chkpub.checked = true;
            }
        }
        /* ]]> */
    </script>
    <p><?php
    echo $_lang['access_permissions_docs_message'];
?></p>
    <ul>
    <?php
    echo implode("\n", $permissions) . "\n";
?>
    </ul>
</div><!--div class="tab-page" id="tabAccess"-->
<?php
  } // !empty($permissions)
  elseif ($_SESSION['mgrRole'] != 1 && ($permissions_yes == 0 && $permissions_no > 0) && ($_SESSION['mgrPermissions']['access_permissions'] == 1 || $_SESSION['mgrPermissions']['web_access_permissions'] == 1)) {
?>
    <p><?php
    echo $_lang["access_permissions_docs_collision"];
?></p>
<?php
    
  }
}
/* End Document Access Permissions *
 ***********************************/
?>

<input type="submit" name="save" style="display:none" />
<?php

// invoke OnDocFormRender event
$evtOut = $modx->invokeEvent('OnDocFormRender', array(
  'id' => $id
));
if (is_array($evtOut))
  echo implode('', $evtOut);
?>
</div><!--div class="tab-pane" id="documentPane"-->
</div><!--div class="sectionBody"-->
</fieldset>
</form>

<script type="text/javascript">
    storeCurTemplate();
</script>
<?php
if (($content['richtext'] == 1 || $_REQUEST['a'] == '4' || $_REQUEST['a'] == '72') && $use_editor == 1) {
  if (is_array($replace_richtexteditor)) {
    // invoke OnRichTextEditorInit event
    $evtOut = $modx->invokeEvent('OnRichTextEditorInit', array(
      'editor' => $which_editor,
      'elements' => $replace_richtexteditor
    ));
    if (is_array($evtOut))
      echo implode('', $evtOut);
  }
}

function getDefaultTemplate() {
  global $modx;
  
  switch ($modx->config['auto_template_logic']) {
    case 'sibling':
      if (!isset($_GET['pid']) || empty($_GET['pid'])) {
        $site_start = $modx->config['site_start'];
        $where      = "sc.isfolder=0 AND sc.id!='{$site_start}'";
        $sibl       = $modx->getDocumentChildren($_REQUEST['pid'], 1, 0, 'template', $where, 'menuindex', 'ASC', 1);
        if (isset($sibl[0]['template']) && $sibl[0]['template'] !== '')
          $default_template = $sibl[0]['template'];
      } else {
        $sibl = $modx->getDocumentChildren($_REQUEST['pid'], 1, 0, 'template', 'isfolder=0', 'menuindex', 'ASC', 1);
        if (isset($sibl[0]['template']) && $sibl[0]['template'] !== '')
          $default_template = $sibl[0]['template'];
        else {
          $sibl = $modx->getDocumentChildren($_REQUEST['pid'], 0, 0, 'template', 'isfolder=0', 'menuindex', 'ASC', 1);
          if (isset($sibl[0]['template']) && $sibl[0]['template'] !== '')
            $default_template = $sibl[0]['template'];
        }
      }
      break;
    case 'parent':
      if (isset($_REQUEST['pid']) && !empty($_REQUEST['pid'])) {
        $parent = $modx->getPageInfo($_REQUEST['pid'], 0, 'template');
        if (isset($parent['template']))
          $default_template = $parent['template'];
      }
      break;
    case 'system':
    default: // default_template is already set
      $default_template = $modx->config['default_template'];
  }
  if (!isset($default_template))
    $default_template = $modx->config['default_template']; // default_template is already set
  
  return $default_template;
}
