<?php
/**
 * ドキュメントの初期化プラグイン
 *
 * @copyright   Copyright &copy; 2007, Katsumi Saito <katsumi@jo1upk.ymt.prug.or.jp>
 * @version     $Id: login.php,v 0.4.1 2010/12/26 16:52:00 Logue Exp $
 * @license     http://opensource.org/licenses/gpl-license.php GNU Public License (GPL2)
 */
use PukiWiki\Auth\Auth;
function plugin_doc_ini_init()
{
	$msg = array(
	'_doc_ini_msg' => array(
		'title_confirmation'	=> T_('Confirmation of page initialization'),	// ページ初期化の確認
		'msg_confirmation'		=> T_('May I really initialize the page of %s'),	// %s のページを本当に初期化してもよろしいでしょうか？
		'title_end'				=> T_('Initialization ended'),			// 初期化終了
		'msg_end'				=> T_('The initialization of %s ended.'),
		'msg_abend_diff'		=> T_('It failed in the initialization of the DIFF file.'),
		'msg_abend_backup'		=> T_('It failed in the initialization of the BACKUP file.'),
		'btn_init'				=> T_('DOC INI'),
		'btn_exec'				=> T_('EXEC'),
		)
	);
        set_plugin_messages($msg);
}

function plugin_doc_ini_convert()
{
	global $vars,$_doc_ini_msg;

	if (Auth::check_role('role_contents_admin')) return '';
	if (empty($vars['page'])) return '';
	if (! doc_ini_file_exist($vars['page'])) return '';
	$script = get_script_uri();

	// ボタンを表示するだけ
	$rc = <<<EOD
<form action="$script" method="post" class="doc_ini_form">
	<input type="hidden" name="cmd" value="doc_ini" />
	<input type="hidden" name="action" value="delete" />
	<input type="hidden" name="page" value="{$vars['page']}" />
	<input class="btn btn-danger" type="submit" value="{$_doc_ini_msg['btn_init']}" />
</form>

EOD;
        return $rc;
}

function plugin_doc_ini_action()
{
	global $vars,$_doc_ini_msg;

	if (Auth::check_role('role_contents_admin')) die_message('NOT AUTHORIZED.');
	if (empty($vars['page'])) return;
	if (! is_pagename($vars['page'])) return '';	// Invalid page name;

	$action = (empty($vars['action'])) ? '' : $vars['action'];
	$retval = array();

	$msg_title = sprintf($_doc_ini_msg['msg_confirmation'],$vars['page']);

	if ($action === 'exec') {
		return plugin_doc_ini_exec($vars['page']);
	}

	$script = get_script_uri();
	$retval['body'] = <<<EOD
<form action="$script" method="post" class="doc_ini_form">
	<input type="hidden" name="cmd" value="doc_ini" />
	<input type="hidden" name="action" value="exec" />
	<input type="hidden" name="page" value="{$vars['page']}" />
	$msg_title
	<input class="btn btn-primary" type="submit" value="{$_doc_ini_msg['btn_exec']}" />
</form>

EOD;
	$retval['msg'] = $_doc_ini_msg['title_confirmation'];
	return $retval;
}

function plugin_doc_ini_exec($page)
{
	global $_doc_ini_msg;

	$backup = $diff = true;

	if (_backup_file_exists($page)) $backup = _backup_delete($page);

	$filename = DIFF_DIR . encode($page) . '.txt';
	if (file_exists($filename)) $diff = unlink($filename);

	if ($backup && $diff) {
		$msg_body = sprintf($_doc_ini_msg['msg_end'],$page);
		return array('msg'=> $_doc_ini_msg['title_end'],'body'=> $msg_body);
	}

	$msg_body = '<ul>';
	if (! $backup) $msg_body .= '<li>'.$_doc_ini_msg['msg_abend_backup'].'</li>';
	if (! $diff)   $msg_body .= '<li>'.$_doc_ini_msg['msg_abend_diff'].'<li>';
	$msg_body .= '</ul>';
	return array('msg'=> $_doc_ini_msg['title_end'],'body'=> $msg_body);
}

function doc_ini_file_exist($page)
{
	$backup = _backup_file_exists($page);
	$filename = DIFF_DIR . encode($page) . '.txt';
	$diff = file_exists($filename);
	return ($backup || $diff);
}

/* End of file doc_ini.inc.php */
/* Location: ./wiki-common/plugin/doc_ini.inc.php */
