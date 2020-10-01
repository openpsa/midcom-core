<?php
$prefix = $data['router']->generate('result');

if ($data['max_pages'] > 1) {
    ?>

<p class="midcom_helper_search_result_nav">
<?php
    $query = [
        'query' => $data['query'],
        'type' => $data['type']
    ];
    if ($data['type'] == 'advanced') {
        $query['topic'] = $data['request_topic'];
        $query['component'] = $data['component'];
        $query['lastmodified'] = $data['lastmodified'];
    }
    $querystring = '?' . http_build_query($query);

    echo $data['l10n']->get('pages') . ': ';

    if ($data['page'] > 1) {
        $page = urlencode($data['page'] - 1);
        $url = "{$prefix}{$querystring}&page={$page}";
        $desc = $data['l10n']->get('previous page');
        echo "<a href='{$url}'>&lArr; {$desc}</a>&nbsp;&nbsp;&nbsp;";
    }

    for ($i = 1; $i <= $data['max_pages']; $i++) {
        if ($i == $data['page']) {
            echo "$i ";
        } else {
            $url = "{$prefix}{$querystring}&page={$i}";
            echo "<a href='{$url}'>${i}</a> ";
        }
    }

    if ($data['page'] < $data['max_pages']) {
        $page = $data['page'] + 1;
        $url = "{$prefix}{$querystring}&page={$page}";
        $desc = $data['l10n']->get('next page');
        echo "&nbsp;&nbsp;&nbsp;<a href='{$url}'>{$desc} &rArr;</a>";
    } ?>
</p>
<?php

}
?>