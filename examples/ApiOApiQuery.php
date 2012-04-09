<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
  <!--
  A Php Proxy to querying overpass-api using class OSM_Api.
  -->
  <head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8" lang="en"></meta>
    <title>OSM Server Side Script</title>
  </head>
  <body>

    <h1>Overpass-API Query Form</h1>

		<?php
		
			require_once(__DIR__.'/../lib/OSM/Api.php');
			/**
			 * You can change the Overpass-API with option 'oapi_url'.
			 * $osmop = new OSM_Api( array( 'oapi_url'=>'http://anotherinterpreter.org' ) );
			 */
			$osmop = new OSM_Api();
			$oapiUrl = $osmop->getOption('oapi_url');
		?>

		<p>A simple proxy to overpass-api using class OSM_Api at <a href="<?php echo $oapiUrl ?>"><?php echo $oapiUrl ?></a><br/>
			Documentation:
			<a href="http://wiki.openstreetmap.org/wiki/Overpass_API">Overpass_API</a>,
			<a href="http://wiki.openstreetmap.org/wiki/Overpass_API/Language_Guide">Overpass_API/Language_Guide</a>
		</p>
		<?php
		//echo 'get_magic_quotes_gpc(): ' . (get_magic_quotes_gpc() ? 'true' : 'false');

		$xmlResult = null;
		$xmlQuery = null;
		if (array_key_exists('xmlquery', $_POST) && trim($_POST['xmlquery']) != "")
		{
			if (get_magic_quotes_gpc())
			{
				$xmlQuery = stripslashes($_POST['xmlquery']);
			}
			else
			{
				$xmlQuery = $_POST['xmlquery'];
			}
			$timeStart = microtime(true);
			$response = $osmop->queryOApi($xmlQuery);
			$timeEnd = microtime(true);
			
			$loadedBytes = $osmop->getStatsLoadedBytes();
			
			$xmlResult = $osmop->getLastLoadedXmlString();
		}
		?>

    <form method="post" accept-charset="UTF-8">
      <p>
        <textarea name="xmlquery" rows="25" cols="80"><?php
				if ($xmlQuery != null)
				{
					echo htmlspecialchars($xmlQuery);
				}
				else
				{
					echo htmlspecialchars('
<osm-script>
 <query type="node">
  <has-kv k="name" v="Paris"/>
  <has-kv k="is_capital" v="France"/>
 </query>
 <print/>
</osm-script>
					');
				}
		?></textarea>
        <input type="submit" value="Query"/>
      </p>
    </form>

    <div>
			<?php
			if ($xmlQuery != null)
			{
				echo '<p>Queried in '.number_format($timeEnd-$timeStart,3).' secondes, loaded '.number_format($loadedBytes,0,',',' ').' bytes.'.'</p>' ;
				echo '<p>Query:</p>';
				echo '<pre>' . $oapiUrl . '?data=' . urlencode($xmlQuery) . '</pre>';
			}
			?>
			<?php
			if ($xmlResult != null)
			{
				echo '<p>Result:</p>';
				echo '<pre>' . htmlspecialchars($xmlResult) . '</pre>';
			}
			?>
    </div>

  </body>
</html>
