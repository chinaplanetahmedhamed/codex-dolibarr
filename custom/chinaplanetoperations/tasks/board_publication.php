<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') define('CSRFCHECK_WITH_TOKEN', 1);
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/../lib/main.lib.php';
require_once __DIR__.'/../class/InternalTaskBoardsService.class.php';
require_once __DIR__.'/../class/ProjectBoardPublication.class.php';

cpo_task_require($db, $user);
header('Cache-Control: private, no-store');
$langs->load('chinaplanetoperations@chinaplanetoperations');
$id = GETPOSTINT('id');
$publication = new ProjectBoardPublication($db, $user, $conf->entity);
$backUrl = dol_buildpath('/chinaplanetoperations/tasks/boards.php', 1).'?id='.$id;
$pageUrl = dol_buildpath('/chinaplanetoperations/tasks/board_publication.php', 1);
$language = GETPOST('lang', 'aZ09');
$languageQuery = in_array($language, ['ar_SA', 'en_US', 'zh_CN'], true) ? '&lang='.$language : '';
$backUrl .= $languageQuery;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (GETPOST('errorcode', 'aZ09') === 'InvalidToken') accessforbidden();
    try {
        $package = $_POST['selection'] ?? null;
        if (!is_string($package) || strlen($package) > 1048576) throw new InvalidArgumentException();
        $changes = json_decode($package, true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($changes) || !array_is_list($changes)) throw new InvalidArgumentException();
        $publication->saveBatch($id, GETPOSTINT('project'), GETPOSTINT('customer'), $changes);
        setEventMessages($langs->trans('CPOBoardPublicationSaved'), null, 'mesgs');
    } catch (InternalTaskAccessException $error) {
        accessforbidden();
    } catch (InternalTaskConflictException $error) {
        setEventMessages($langs->trans('InternalTaskStale'), null, 'errors');
    } catch (Throwable $error) {
        setEventMessages($langs->trans('InternalTaskValidationError'), null, 'errors');
    }
    header('Location: '.$pageUrl.'?id='.$id.$languageQuery, true, 303);
    exit;
}
try {
    $review = $publication->board($id);
} catch (InternalTaskAccessException $error) {
    accessforbidden();
}
$board = $review['board'];
$project = $board['workspace_project'];
$title = $langs->trans('CPOBoardPublishProject');
llxHeader('', $title, '', '', 0, 0, [cpo_asset('/custom/chinaplanetoperations/js/board_publication.js')],
    [cpo_asset('/custom/chinaplanetoperations/css/operations.css')]);
?>
<main class="cpo-page">
  <a href="<?php echo dol_escape_htmltag($backUrl); ?>"><?php echo $langs->trans('CPOBoardBackToProjectBoard'); ?></a>
  <h1><?php echo $title; ?></h1>
  <p><bdi><?php echo dol_escape_htmltag($project['title']); ?></bdi></p>
  <p><?php echo $langs->trans('CPOBoardPublicationReview'); ?></p>
  <?php if (!$review['cards']) { ?>
    <p><?php echo $langs->trans('CPOBoardPublicationNoCards'); ?></p>
  <?php } else { ?>
  <form method="post" action="<?php echo dol_escape_htmltag($pageUrl); ?>" data-board-publication>
    <input type="hidden" name="token" value="<?php echo dol_escape_htmltag(newToken()); ?>">
    <input type="hidden" name="id" value="<?php echo $id; ?>">
    <?php if ($languageQuery !== '') { ?>
    <input type="hidden" name="lang" value="<?php echo dol_escape_htmltag($language); ?>">
    <?php } ?>
    <input type="hidden" name="project" value="<?php echo (int) $project['rowid']; ?>">
    <input type="hidden" name="customer" value="<?php echo (int) $project['fk_soc']; ?>">
    <input type="hidden" name="selection">
    <div class="cpo-task-action">
      <label><input type="checkbox" data-publication-all> <?php echo $langs->trans('CPOBoardSelectAllCurrent'); ?></label>
      <button type="button" class="button" data-publication-copy><?php echo $langs->trans('CPOBoardDraftCardTitles'); ?></button>
    </div>
    <div class="div-table-responsive">
      <table class="noborder centpercent">
        <thead><tr class="liste_titre">
          <th scope="col"><?php echo $langs->trans('CPOBoardShowCard'); ?></th>
          <th scope="col"><?php echo $langs->trans('Task'); ?></th>
          <th scope="col"><?php echo $langs->trans('CPOBoardCustomerTitle'); ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($review['cards'] as $card) {
            $publicTitle = (string) $card->public_title;
            $taskId = (int) $card->task_id;
        ?>
          <tr class="oddeven" data-publication-task="<?php echo $taskId; ?>" data-version="<?php echo (int) $card->version; ?>">
            <td><input type="checkbox" data-publication-visible aria-labelledby="publication-card-<?php echo $taskId; ?>"<?php echo $publicTitle !== '' ? ' checked' : ''; ?>></td>
            <th scope="row"><bdi id="publication-card-<?php echo $taskId; ?>"><?php echo dol_escape_htmltag($card->internal_title); ?></bdi><br>
              <small><bdi><?php echo dol_escape_htmltag($card->list_title); ?></bdi></small></th>
            <td><input class="flat minwidth200" data-publication-title maxlength="180" dir="auto"
              aria-label="<?php echo dol_escape_htmltag($langs->trans('CPOBoardCustomerTitle').' · '.$card->internal_title); ?>"
              value="<?php echo dol_escape_htmltag($publicTitle); ?>"<?php echo $publicTitle !== '' ? ' required' : ''; ?>></td>
          </tr>
        <?php } ?>
        </tbody>
      </table>
    </div>
    <button type="submit" class="button button-save" disabled data-publication-submit><?php echo $langs->trans('Save'); ?></button>
  </form>
  <?php } ?>
</main>
<?php llxFooter(); $db->close(); ?>
