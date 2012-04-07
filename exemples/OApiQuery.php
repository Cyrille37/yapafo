<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
  <!--
  Proxy to querying overpass-api.
  exemple:
  http://cyrille.giquello.fr/labs/carto/overpass-api/query.php

  -->
  <head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8" lang="en"></meta>
    <title>OSM Server Side Script</title>
  </head>
  <body>

    <h1>Overpass-API Query Form</h1>

		<?php
		
			require_once(__DIR__.'/../../OSMPhpLib/OSM/OAPI.php');
			//$osmop = new OSM_OAPI( array('url'=>OSM_OAPI::OAPI_URL_DE) );
			$osmop = new OSM_OAPI();
			$oapiUrl = $osmop->getUrl();
		?>

		<p>A simple proxy to overpass-api at <a href="<?php echo $oapiUrl ?>"><?php echo $oapiUrl ?></a><br/>
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
			$response = $osmop->request($xmlQuery);
			$timeEnd = microtime(true);
			$loadedBytes = $osmop->getStatsLoadedBytes();
			
			$xmlResult = $response->asXML();
		}
		?>

    <form method="post" accept-charset="UTF-8">
      <p>
        <textarea name="xmlquery" rows="25" cols="80"><?php
		if ($xmlQuery != null)
		{
			echo htmlspecialchars($xmlQuery);
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
