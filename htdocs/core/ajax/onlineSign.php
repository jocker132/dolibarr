<?php
/*
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	\file       /htdocs/core/ajax/onlineSign.php
 *	\brief      File to make Ajax action on Knowledge Management
 */

if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', '1'); // Disables token renewal
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}
if (!defined('NOREQUIRESOC')) {
	define('NOREQUIRESOC', '1');
}
if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', '1');
}
// Do not check anti CSRF attack test
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
// If there is no need to load and show top and left menu
if (!defined("NOLOGIN")) {
	define("NOLOGIN", '1');
}
if (!defined('NOIPCHECK')) {
	define('NOIPCHECK', '1'); // Do not check IP defined into conf $dolibarr_main_restrict_ip
}
if (!defined('NOBROWSERNOTIF')) {
	define('NOBROWSERNOTIF', '1');
}
include '../../main.inc.php';

$action = GETPOST('action', 'aZ09');

$signature = GETPOST('signaturebase64');
$ref = GETPOST('ref', 'aZ09');
$mode = GETPOST('mode', 'aZ09');
$SECUREKEY = GETPOST("securekey"); // Secure key

$error = 0;
$response = "";

// Check securitykey
$securekeyseed = $conf->global->PROPOSAL_ONLINE_SIGNATURE_SECURITY_TOKEN;
$type = $mode;
$calculatedsecuritykey = dol_hash($securekeyseed.$type.$ref, '0');

if ($calculatedsecuritykey != $SECUREKEY) {
	http_response_code(403);
	print 'Bad value for securitykey. Value provided '.dol_escape_htmltag($SECUREKEY).' does not match expected value for ref='.dol_escape_htmltag($ref);
	exit(-1);
}


/*
 * Actions
 */

// None


/*
 * View
 */

if ($action == "importSignature") {
	if (!empty($signature) && $signature[0] == "image/png;base64") {
		$signature = $signature[1];
		$data = base64_decode($signature);

		if ($mode == "propale" || $mode == 'proposal') {
			require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
			require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
			$object = new Propal($db);
			$object->fetch(0, $ref);

			$upload_dir = !empty($conf->propal->multidir_output[$object->entity])?$conf->propal->multidir_output[$object->entity]:$conf->propal->dir_output;
			$upload_dir .= '/'.dol_sanitizeFileName($object->ref).'/';

			$date = dol_print_date(dol_now(), "%Y%m%d%H%M%S");
			$filename = "signatures/".$date."_signature.png";
			if (!is_dir($upload_dir."signatures/")) {
				if (!dol_mkdir($upload_dir."signatures/")) {
					$response ="Error mkdir. Failed to create dir ".$upload_dir."signatures/";
					$error++;
				}
			}

			if (!$error) {
				$return = file_put_contents($upload_dir.$filename, $data);
				if ($return == false) {
					$error++;
					$response = 'error file_put_content';
				}
			}

			if (!$error) {
				$pdf = pdf_getInstance();
				$pdf->Open();
				$pdf->AddPage();
				$pagecount = $pdf->setSourceFile($upload_dir.$ref.".pdf");

				$tppl = $pdf->importPage(1);
				$pdf->useTemplate($tppl);
				$pdf->Image($upload_dir.$filename, 129, 239.6, 60, 15);
				$pdf->Close();
				$pdf->Output($upload_dir.$ref."_signed-".$date.".pdf", "F");

				$sql  = "UPDATE ".MAIN_DB_PREFIX."propal";
				$sql .= " SET fk_statut = ".((int) $object::STATUS_SIGNED).", note_private = '".$object->note_private."', date_signature='".$db->idate(dol_now())."'";
				$sql .= " WHERE rowid = ".((int) $object->id);

				dol_syslog(__METHOD__, LOG_DEBUG);
				$resql = $db->query($sql);
				if (!$resql) {
					$error++;
				} else {
					$num = $db->affected_rows($resql);
				}

				if (!$error) {
					$db->commit();
					$response = "success";
					setEventMessages("PropalSigned", null, 'warnings');
				} else {
					$db->rollback();
					$error++;
					$response = "error sql";
				}
			}
		}
	} else {
		$error++;
		$response = 'error signature_not_found';
	}
}

if ($error) {
	http_response_code(501);
}

echo $response;
