<?php
$prefix = midcom_core_context::get()->get_key(MIDCOM_CONTEXT_ANCHORPREFIX);
?>
<h1><?php echo $data['view_title']; ?></h1>

<table>
    <thead>
        <?php
        echo "<th>" . $data['l10n']->get('folders') . "</th>\n";
        foreach ($data['privileges'] as $privilege) {
            echo "<th>" . $privilege . "</th>\n";
        }
        ?>
    </thead>
    <tbody>
        <?php
        foreach ($data['objects'] as $guid => $privs) {
            try {
                $object = new midcom_db_topic($guid);
            } catch (midcom_error) {
                continue;
            }
            echo "<tr>\n";
            echo "<th><a href=\"{$prefix}__mfa/asgard/object/permissions/{$object->guid}/\">{$object->extra}</a></th>\n";

            foreach (array_keys($data['privileges']) as $privilege) {
                echo "<td>";
                if (!isset($privs[$privilege])) {
                    echo "&nbsp;</td>\n";
                    continue;
                }

                if ($privs[$privilege] == MIDCOM_PRIVILEGE_ALLOW) {
                    echo $data['l10n_midcom']->get('yes');
                } elseif ($privs[$privilege] == MIDCOM_PRIVILEGE_DENY) {
                    echo $data['l10n_midcom']->get('no');
                }

                echo "</td>\n";
            }
            echo "</tr>\n";
        }
        ?>
    </tbody>
</table>