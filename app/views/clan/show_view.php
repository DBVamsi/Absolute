<?php // app/views/clan/show_view.php ?>

<?php // Display feedback messages if any
if (!empty($view_feedback_message['text'])): ?>
    <div class='panel content'>
        <div class='head'><?= ($view_feedback_message['type'] == 'success' ? 'Success' : 'Error') ?></div>
        <div class='body' style='padding: 10px; text-align: center; color: <?= ($view_feedback_message['type'] == 'success' ? 'green' : 'red') ?>;'>
            <?= htmlspecialchars($view_feedback_message['text'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
        </div>
    </div>
<?php endif; ?>


<?php if ($view_can_create_clan && empty($view_clan_data) && $User_Data['Clan'] == 0): // Check User_Data again in case session didn't update in same request for view ?>
    <div class='panel content'>
        <div class='head'>Create A Clan</div>
        <div class='body' style='padding: 5px;'>
            <div class='description'>
                You may create a clan at the cost of $<?= htmlspecialchars(number_format($view_creation_cost), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>.
            </div>
            <form method='POST' action='<?= htmlspecialchars(DOMAIN_ROOT . "/clan.php", ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>'>
                <input type="hidden" name="create_clan_action" value="1" />
                <input type="text" name="clan_name" placeholder='Clan Name' style="margin: 5px 0;" required />
                <br />
                <input type='submit' value='Create Clan' />
            </form>
        </div>
    </div>

<?php elseif (!empty($view_clan_data)): ?>
    <?php
        // Prepare clan data for display
        $clan_name_esc = htmlspecialchars($view_clan_data['Name'] ?? 'Unnamed Clan', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $clan_avatar_esc = !empty($view_clan_data['Avatar']) ? htmlspecialchars($view_clan_data['Avatar'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
        $clan_signature_esc = !empty($view_clan_data['Signature']) ? nl2br(htmlspecialchars($view_clan_data['Signature'], ENT_QUOTES | ENT_HTML5, 'UTF-8')) : 'This clan has no signature set.';
        $clan_level_esc = number_format(FetchLevel($view_clan_data['Experience_Raw'] ?? 0, 'Clan'));
        $clan_exp_esc = htmlspecialchars($view_clan_data['Experience'] ?? '0', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $clan_points_esc = htmlspecialchars($view_clan_data['Clan_Points'] ?? '0', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    ?>
    <div class='panel content'>
        <div class='head'>
            <?= $clan_name_esc ?>'s Clan Home
        </div>
        <div class='body' style='padding: 5px;'>
            <div class='flex'>
                <div style='flex-basis: 50%; padding-right: 10px;'> {/* Left Column for Clan Details & Options */}
                    <table class='border-gradient' style='width: 100%;'>
                        <tbody>
                            <tr>
                                <td colspan='2' style='height: 150px; width: 50%; text-align: center; vertical-align: middle;'>
                                    <?= ($clan_avatar_esc ? "<img src='{$clan_avatar_esc}' alt='{$clan_name_esc} Avatar' style='max-width:150px; max-height:150px;' />" : 'This clan has no avatar set.') ?>
                                </td>
                                <td colspan='2' style='height: 150px; width: 50%; text-align: center; vertical-align: middle; padding: 5px;'>
                                    <div style="max-height: 140px; overflow-y: auto;"><?= $clan_signature_esc ?></div>
                                </td>
                            </tr>
                        </tbody>
                        <thead><tr><th colspan='4'>Statistics</th></tr></thead>
                        <tbody>
                            <tr><td><b>Clan Level</b></td><td><?= $clan_level_esc ?></td><td><b>Clan Experience</b></td><td><?= $clan_exp_esc ?></td></tr>
                            <tr><td><b>Clan Points</b></td><td><?= $clan_points_esc ?></td><td>&nbsp;</td><td>&nbsp;</td></tr>
                        </tbody>
                        <thead><tr><th colspan='4'>Currencies</th></tr></thead>
                        <tbody>
                            <tr>
                                <?php foreach ($Constants->Currency as $Currency): ?>
                                    <td colspan='2' style="text-align: center;"><img src='<?= htmlspecialchars($Currency['Icon'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>' alt="<?= htmlspecialchars($Currency['Name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>" /></td>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <?php foreach ($Constants->Currency as $Currency): ?>
                                    <td colspan='2' style="text-align: center;"><?= htmlspecialchars(number_format($view_clan_data[$Currency['Value']] ?? 0), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></td>
                                <?php endforeach; ?>
                            </tr>
                        </tbody>
                    </table>

                    <?php if ($view_user_clan_role): // Current user is part of this clan ?>
                        <table class='border-gradient' style='margin-top: 10px; width: 100%;'>
                            <thead><tr><th colspan='2'><b>Clan Options</b></th></tr></thead>
                            <tbody>
                                <tr>
                                    <td style='width: 50%;'><a href='<?= DOMAIN_ROOT ?>/clan/leave.php'>Leave Clan</a></td>
                                    <td style='width: 50%;'><a href='<?= DOMAIN_ROOT ?>/clan/donate.php'>Donate to Clan</a></td>
                                </tr>
                            </tbody>
                        </table>

                        <?php if (in_array($view_user_clan_role, ['Moderator', 'Administrator'])): ?>
                            <table class='border-gradient' style='margin-top: 5px; width: 100%;'>
                                <thead><tr><th colspan='2'><b>Moderator Options</b></th></tr></thead>
                                <tbody>
                                    <tr><td><a href='<?= DOMAIN_ROOT ?>/clan/manage_applications.php'>Manage Applications</a></td><td><a href='<?= DOMAIN_ROOT ?>/clan/invite_members.php'>Invite Members</a></td></tr>
                                    <tr><td><a href='<?= DOMAIN_ROOT ?>/clan/manage_members.php'>Manage Members</a></td><td><a href='<?= DOMAIN_ROOT ?>/clan/manage_clan.php'>Manage Clan (Edit Profile)</a></td></tr>
                                    <tr><td><a href='<?= DOMAIN_ROOT ?>/clan/upgrades.php'>Clan Upgrades</a></td><td><a href='<?= DOMAIN_ROOT ?>/clan/send_message.php'>Send Clan Announcement</a></td></tr>
                                </tbody>
                            </table>
                        <?php endif; ?>

                        <?php if ($view_user_clan_role == 'Administrator'): ?>
                            <table class='border-gradient' style='margin-top: 5px; width: 100%;'>
                                <thead><tr><th colspan='2'><b>Administrator Options</b></th></tr></thead>
                                <tbody>
                                    <tr><td><a href='<?= DOMAIN_ROOT ?>/clan/disband.php'>Disband Clan</a></td><td><a href='<?= DOMAIN_ROOT ?>/clan/ownership.php'>Transfer Ownership</a></td></tr>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div style='flex-basis: 50%; padding-left: 10px;'> {/* Right Column for Member List */}
                    <table class='border-gradient' style='width: 100%;'>
                        <thead>
                            <tr><th colspan='4'>Clan Members (<?= count($view_members_list) ?>)</th></tr>
                            <tr>
                                <td style='width: 10%;'></td>
                                <td style='width: 40%;'><b>Member</b></td>
                                <td style='width: 30%;'><b>Clan Title</b></td>
                                <td style='width: 20%; text-align:right;'><b>Clan Experience</b></td>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($view_members_list)): ?>
                                <tr><td colspan='4' style='text-align:center; padding: 10px;'>This clan has no members.</td></tr>
                            <?php else: ?>
                                <?php foreach ($view_members_list as $member): ?>
                                    <?php
                                        $member_username_esc = htmlspecialchars($member['Username'] ?? 'Unknown', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                        $member_avatar_esc = !empty($member['Avatar']) ? htmlspecialchars(DOMAIN_SPRITES . $member['Avatar'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : htmlspecialchars(DOMAIN_SPRITES . '/Avatars/default.png', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                        $member_profile_url = htmlspecialchars(DOMAIN_ROOT . "/profile.php?id=" . ($member['ID'] ?? 0), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                        $member_clan_rank_esc = htmlspecialchars($member['Clan_Rank'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                        $member_clan_title_esc = htmlspecialchars($member['Clan_Title'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                        $member_clan_exp_esc = htmlspecialchars(number_format($member['Clan_Exp'] ?? 0), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                    ?>
                                    <tr>
                                        <td style='text-align:center;'><img src='<?= $member_avatar_esc ?>' alt='<?= $member_username_esc ?>' style='max-width: 32px; max-height: 32px;' /></td>
                                        <td><a href='<?= $member_profile_url ?>'><b class='<?= strtolower($member_clan_rank_esc) ?>'><?= $member_username_esc ?></b></a></td>
                                        <td><?= $member_clan_title_esc ?></td>
                                        <td style='text-align:right;'><?= $member_clan_exp_esc ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php elseif (!$view_can_create_clan && empty($view_feedback_message['text'])): // Not allowed to create and no clan data found (e.g. invalid clan_id in GET) ?>
    <div class='panel content'>
        <div class='head'>Clan Not Found</div>
        <div class='body' style='padding: 10px; text-align: center;'>
            The clan you are looking for does not exist, or you are not part of any clan.
            <?php if ($User_Data['Clan'] == 0): // Offer to go to creation page if they are truly clanless ?>
                <br/>Perhaps you'd like to <a href='<?= htmlspecialchars(DOMAIN_ROOT . "/clan.php", ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>'>create one</a>?
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<style>
    /* Basic styles for clan member ranks if they exist from main CSS */
    .administrator { color: red; font-weight: bold; }
    .moderator { color: blue; }
    /* .member { color: black; } */ /* Default */
</style>
