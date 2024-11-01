
/**
 *
 *	Copyright Probaris Technologies, Inc. 2015
 *	
 *	This file is part of WPPrivaKeySignOn.
 *	WPPrivaKeySignOn is free software: you can redistribute it and/or modify
 *	it under the terms of the GNU General Public License as published by
 *	the Free Software Foundation, either version 3 of the License, or
 *	(at your option) any later version.
 *
 *	WPPrivaKeySignOn is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU General Public License for more details.
 *
 *	You should have received a copy of the GNU General Public License
 *	along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
*/

var original_tb_remove = tb_remove;

tb_remove = function (param) {
	original_tb_remove();
	if (typeof param === 'string' && param != null && param != '') {
		window.location.href = param;
	} else {
		window.location.reload();
	}
	return false;
}


function privakey_confirmrevert() {
	var modalTrigger = document.getElementById("privakey_modal");
	var confirm = document.getElementById("privakey_popoverbutton");
	var cancel = document.getElementById("privakey_popoverbuttoncancel");
	var content = document.getElementById("privakey_popovercontent");

	if (modalTrigger != null && confirm != null && content != null) {
		content.innerHTML = "Are you sure you wish to unbind this user's PrivaKey account?";
		confirm.value = "Confirm";
		cancel.style.display = 'inline';
		confirm.onclick = function () { window.location.href = window.location.href.replace('privakey_unbinding=confirm', 'privakey_unbinding=true'); }
		jQuery(document).ready(function () {
			jQuery(modalTrigger).trigger("click");
		});
	}
}

