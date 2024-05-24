<?php
echo "<h1>{$data['view_title']}</h1>\n";

if ($history = $data['history']->all()) {
    midcom::get()->head->add_jsfile(MIDCOM_STATIC_URL . '/midcom.services.rcs/rcs.js');
    $guid = $data['guid'];
    ?>
    <form name="midcom_admin_rcs_history" action="" >
        <table>
            <thead>
                <tr>
                    <th colspan="2"></th>
                    <th><?php echo $data['l10n']->get('revision'); ?></th>
                    <th><?php echo $data['l10n']->get('date'); ?></th>
                    <th><?php echo $data['l10n']->get('user'); ?></th>
                    <th><?php echo $data['l10n']->get('lines'); ?></th>
                    <th><?php echo $data['l10n']->get('message'); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php
            $formatter = $data['l10n']->get_formatter();
    foreach ($history as $number => $revision) {
        echo "                <tr>\n";
        echo "                    <td><input type=\"radio\" name=\"first\" value=\"{$number}\" />\n";
        echo "                    <td><input type=\"radio\" name=\"last\" value=\"{$number}\" />\n";
        echo "                    <td><a href='" . $data['router']->generate('preview', ['guid' => $guid, 'revision' => $number]) . "'>{$revision['version']}</a></td>\n";
        echo "                    <td>" . $formatter->datetime($revision['date']) . "</td>\n";

        if (   $revision['user']
            && $user = midcom::get()->auth->get_user($revision['user'])) {
            $person_label = $user->get_storage()->name;
            echo "                    <td>{$person_label}</td>\n";
        } elseif ($revision['ip']) {
            echo "                    <td>{$revision['ip']}</td>\n";
        } else {
            echo "                    <td></td>\n";
        }
        echo "                    <td>{$revision['lines']}</td>\n";
        echo "                    <td>{$revision['message']}</td>\n";
        echo "                    <td></td>\n";
        echo "                </tr>\n";
    } ?>
            </tbody>
        </table>
        <input type="submit" name="f_compare" value="<?php echo $data['l10n']->get('show differences'); ?>" />
    </form>
    <script>
    init_controls('[name="midcom_admin_rcs_history"]');
    </script>
    <?php
} else {
    echo $data['l10n']->get('no revisions exist');
}
?>