<!-- [<?= date("Y-m-d H:i:s"); ?>]-->
<?php foreach ($failedTests as $id => $failedTest): ?>
    <tr>
        <td class="test_details">
            <b>Title:</b> <?= $failedTestsMetadata[$id]['title']; ?>
            <br/><b>File:</b><span class="copy_to_clipboard"
                                   title="click to copy to clipboard"><?= $failedTestsMetadata[$id]['file']; ?></span>
            <br/><b>Reference image:</b>
            <span class="copy_to_clipboard"
                  title="click to copy to clipboard"><?= $failedTestsMetadata[$id]['referenceImagePath']; ?></span>
            <?= $failedTestsMetadata[$id]['referenceImageDeleteLink'] ? "<span data-href=\"{$failedTestsMetadata[$id]['referenceImageDeleteLink']}\" class=\"delete_reference_link\"> [x] Delete reference image</span>" : ""; ?>
            <?= $failedTestsMetadata[$id]['url'] ? "<br/><b>Url: </b><a href=\"{$failedTestsMetadata[$id]['url']}\" target=_blank style=\"text-overflow:ellipsis\">{$failedTestsMetadata[$id]['url']}</a>" : ""; ?>
            <br/><b>Environment:</b> <?= $failedTestsMetadata[$id]['env']; ?>
            <br/><b>Error message:</b> <?= $failedTestsMetadata[$id]['error']; ?>
        </td>
        <td>
            <img src='data:image/png;base64,<?php echo base64_encode(file_get_contents($failedTest->getDeviationImage())); ?>'/>
        </td>
        <td>
            <img src='data:image/png;base64,<?php echo base64_encode(file_get_contents($failedTest->getExpectedImage())); ?>'/>
        </td>
        <td>
            <img src='data:image/png;base64,<?php echo base64_encode(file_get_contents($failedTest->getCurrentImage())); ?>'/>
        </td>
    </tr>
<?php endforeach; ?>
<!--[END_ITEMS]-->