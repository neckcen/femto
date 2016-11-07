<!DOCTYPE html>
<html lang="en" class="no-js">
<head>
	<meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

	<title><?php if($page['title']): ?><?=$page['title'].' - '?><?php endif; ?><?=$site_title?></title>
    <?php if($page['description']): ?><meta name="description" content="<?=$page['description']?>"><?php endif; ?>
    <?php if($page['robots']): ?><meta name="robots" content="<?=$page['robots']?>"><?php endif; ?>

    <link rel="stylesheet" href="<?=$theme_url?>/kube.min.css" type="text/css">
    <link rel="stylesheet" href="<?=$theme_url?>/style.css" type="text/css">
</head>
<body>
	<header>
        <a href="<?=$base_url?>/"><hgroup>
    		<h1><?=$site_title?></h1>
            <h2>minimalist &amp; efficient</h2>
        </hgroup></a>
		<nav class="navbar"><ul>
            <?php foreach(directory('/')->sort('alpha') as $p): ?>
            <li><a href="<?=$p['url']?>"><?=$p['title']?></a></li>
            <?php endforeach; ?>
		</ul></nav>
	</header>

	<section>
		<?==$page['content']?>
	</section>

	<footer>
		Powered by <a href="https://eiky.net/tinkering/femto/">Femto</a>.
	</footer>
</body>
</html>
