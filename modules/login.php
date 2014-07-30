<?php

$login_title = _("Login");

function loginPage() {
?>
<form><table><tr>
    <td><?echo _("User");?>:</td><td><input type="text"/></td>
</tr><tr>
    <td><?echo _("Password");?>:</td><td><input type="password"/></td>
</tr><tr>
    <td colspan="2"><input type="submit" value="Login"/></td>
</tr></table>
</form>
<?}?>