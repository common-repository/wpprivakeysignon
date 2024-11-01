<?php

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

function privaKeyWriteConfigs () {
	global $PrivaKeyConfig;

	$PrivaKeyConfig = array(
//		'idp_address' => "https://mid-appserver.probaris.com/IdentityServerDev",
		'idp_address' => "https://idp.privakey.com",
	);
}
