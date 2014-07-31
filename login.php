<?
    //global $ADEI;
    try {
        //$hybridauth = $ADEI->getHybridAuth();
        $hybrid_config = dirname(__FILE__).'/includes/hybridauth-2.1.2/hybridauth/config.php';
        require_once('includes/hybridauth-2.1.2/hybridauth/Hybrid/Auth.php');
        $hybridauth = new Hybrid_Auth($hybrid_config); 
    }
    catch(Exception $e) {
?>
    <div style="text-align: center;margin: 0 auto;margin-top: 20px;"><b style="color:red; ">Error occured, please, contact administrator</b></div>
<?php
        die();
    }

    //var_dump($first_connected);
    $provider  = @ $_GET["provider"];
    $return_to = @ $_GET["return_to"];
        
    if(!$return_to ){
        echo "Invalid params!";
    }

    if(!empty($provider) && $hybridauth->isConnectedWith($provider)) {
        $return_to = $return_to . ( strpos( $return_to, '?' ) ? '&' : '?' ) . "connected_with=" . $provider ;
        $username = $hybridauth->getAdapter($provider)->getUserProfile()->displayName;

?>

        <script>
            if(window.parent) {
                console.log('close colorbox in isconn');
                window.opener.jQuery("#menu_zone").trigger('login', "<?php echo $username ?>");
                window.close();
                window.opener.jQuery.colorbox.close();
                //return false;
            }
        </script>        
<?
        die();
    }

    if (!empty($provider)) {
        $params = array();

        if( $provider == "OpenID" ){
            $params["openid_identifier"] = @ $_REQUEST["openid_identifier"];
        }


        if (isset($_REQUEST["redirect_to_idp"])) {
            if ($hybridauth->isConnectedWith("twitter")) {
                echo "Already connected";
            }

            $adapter = $hybridauth->authenticate($provider, $params);
            $user = $adapter->getUserProfile();
            $_SESSION['access_token'] = $adapter->getAccessToken()[0];
            $_SESSION['username'] = $user->displayName;
            $_SESSION['email'] = $user->email;
?>
        <script>
            console.log('close colorbox');
            window.parent.jQuery.colorbox.close(); return false;
        </script>
<?            
            //var_dump($_SESSION);
        }
        else {
?>

<table width="100%" border="0">
  <tr>
    <td align="center" height="190px" valign="middle"><img src="includes/logindep/images/loading.gif" /></td>
  </tr>
  <tr>
    <td align="center"><br /><h3>Loading...</h3><br /></td> 
  </tr>
  <tr>
    <td align="center">Contacting <b><?php echo ucfirst( strtolower( strip_tags( $provider ) ) ) ; ?></b>. Please wait.</td> 
  </tr> 
</table>
<script>
    window.location.href = window.location.href + "&redirect_to_idp=1";
</script>

<?
        }
        die();
    }

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" >
<head>

    <script>
    jQuery.noConflict();
    var provider = null;
    var isLoginOpened = false;

    jQuery(function() {
        jQuery(".provider_logo").click(function(event) {
            provider = jQuery(this).attr("idp");
            switch( provider ){
                case "google"  : case "twitter" : case "facebook": case "linkedin" :
                    start_auth( "?provider=" + provider );
                    break;
                case "openid" : 
                    jQuery("#openidm").html( "Please enter your OpenID URL" );
                    jQuery("#openidun").css( "width", "350" );
                    jQuery("#openidimg").attr( "src", "includes/logindep/images/icons/" + provider + ".png" );
                    jQuery("#providers").hide();
                    jQuery("#openidid").show();  
                    break;
                default: alert("no such provider");
            }

        });

        function start_auth( params ){
            start_url = 'login.php' + params + "&return_to=<?php echo urlencode($return_to); ?>" + "&_ts=" + (new Date()).getTime();
            window.open(
                start_url, 
                "hybridauth_social_sing_on", 
                "location=0,status=0,scrollbars=0,width=800,height=500"
            );  
            //window.open(window.location.host);
        }

        jQuery("#backtolist").click(
            function(){
                jQuery("#providers").show();
                jQuery("#openidid").hide();
                return false;
            }
        );  

    })
    </script>

<style>
        #providers {
            text-align: center;
        } 
        .provider_logo{
            cursor: pointer;
            cursor: hand;
        }

</style>

<div id="providers" class="provider_content" >
    <h2 id="loginHeader">ADEI Login</h2>
    <table width="100%" border="0">
        <tr>
            <td align="center"><img class="provider_logo" idp="google" src="includes/logindep/images/icons/google.png" title="google" /></td>
            <td align="center"><img class="provider_logo" idp="twitter" src="includes/logindep/images/icons/twitter.png" title="twitter" /></td>
        </tr>
        <tr>
            <td align="center"><img class="provider_logo" idp="facebook" src="includes/logindep/images/icons/facebook.png" title="facebook" /></td>
            <td align="center"><img class="provider_logo" idp="linkedin" src="includes/logindep/images/icons/linkedin.png" title="linkedin" /></td> 
        </tr>
    </table>

</div>

<div id="openidid" style="display:none;">
        <table width="100%" border="0">
          <tr> 
            <td align="center"><img id="openidimg" src="includes/logindep/images/loading.gif" /></td>
          </tr>  
          <tr> 
            <td align="center"><h3 id="openidm">Please enter your user or blog name</h3></td>
          </tr>  
          <tr>
            <td align="center"><input type="text" name="openidun" id="openidun" style="padding: 5px; margin:7px;border: 1px solid #999;width:240px;" /></td>
          </tr>
          <tr>
            <td align="center">
                <input type="submit" value="Login" id="openidbtn" style="height:33px;width:85px;" />
                <br />
                <small><a href="#" id="backtolist">back</a></small>
            </td>
          </tr>
        </table> 
    </div>



