<!DOCTYPE html>
<html lang="en" class="no-js">
<head>
	<meta charset="utf-8">

	<title><?php if($page['title']) echo $page['title'].' - '; echo $site_title; ?></title>
<?php if($page['description']): ?>
	<meta name="description" content="<?php echo $page['description']; ?>">
<?php endif; if($page['robots']): ?>
	<meta name="robots" content="<?php echo $page['robots']; ?>">
<?php endif; ?>

    <link rel="stylesheet" href="<?php echo $theme_url; ?>/kube.min.css" type="text/css">
    <link rel="stylesheet" href="<?php echo $theme_url; ?>/style.css" type="text/css">
</head>
<body>
	<header>
        <a href="<?php echo $base_url; ?>/"><hgroup>
    		<h1><?php echo $site_title; ?></h1>
            <h2>minimalist &amp; efficient</h2>
        </hgroup></a>
		<nav class="navbar"><ul>
            <?php foreach(\femto\directory('/') as $p): ?>
            <li><a href="<?php echo $base_url.$p['url']; ?>"><?php echo $p['title']; ?></a></li>
            <?php endforeach; ?>
		</ul></nav>
	</header>

	<section>
		<?php echo $page['content']; ?>
	</section>

	<footer>
		Powered by <a href="https://eiky.net/tinkering/femto/">Femto</a>.
	</footer>
</body>
</html>
