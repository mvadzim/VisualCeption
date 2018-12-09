<!DOCTYPE html>
<html>
<head>
    <title>VisualCeption Report</title>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.19/css/jquery.dataTables.css">
    <script type="text/javascript" charset="utf8"
            src="https://code.jquery.com/jquery-2.2.4.min.js"></script>
    <script type="text/javascript" charset="utf8"
            src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.js"></script>
    <style>
        table {
            width: 100%;
        }

        table td {
            vertical-align: top;
            width: 25%;
        }

        img {
            display: block;
            width: 100%;
            cursor: pointer;
        }

        #zoom {
            position: fixed;
            overflow: scroll;
            display: none;
            z-index: 999;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
            border: 4px outset black;
        }

        .copy_to_clipboard {
            cursor: pointer;
            border-bottom: 1px dotted black;
        }

        .test_details {
            word-wrap: break-word;
            word-break: break-all;
        }
    </style>
</head>
<body>
<h1>VisualCeption Report</h1>
<label>Show</label>
<label><input type="checkbox" class="column_visible" value="0" checked="checked"/>Test Details</label>
<label><input type="checkbox" class="column_visible" value="1" checked="checked"/>Deviation Image</label>
<label><input type="checkbox" class="column_visible" value="2" checked="checked"/>Expected Image</label>
<label><input type="checkbox" class="column_visible" value="3" checked="checked"/>Current Image</label>

<table id="table" class="display responsive no-wrap">
    <thead>
    <tr>
        <th>Test Details</th>
        <th>Deviation Image</th>
        <th>Expected Image</th>
        <th>Current Image</th>
    </tr>
    </thead>
    <tbody>
    <!--[ITEMS]-->
    <!--[END_ITEMS]-->
    </tbody>
</table>
<div id="zoom"><img src="" title="click here to hide it"></div>
<script>
    let table;

    $(document).ready(function () {

        $('img').click(function () {
            var imgSrc = $(this).attr("src");
            $('#zoom img').attr({src: imgSrc});
            $('#zoom').fadeIn(300);
        });
        $('#zoom').click(function () {
            $(this).fadeOut();
        });
        $('.copy_to_clipboard').click(function () {
            if (navigator.clipboard) {
                let text = this.innerHTML.trim();
                navigator.clipboard.writeText(text);
            }
        });
        table = $('#table').DataTable(
            {
                "lengthMenu": [[10, 50, -1], [10, 50, "All"]]
            }
        );
    });

    $('.column_visible').change(function () {
        let columnVisible;
        let columnId = this.value;
        if (this.checked) {
            columnVisible = true;
        } else {
            columnVisible = false;
        }
        table.columns(columnId).visible(columnVisible);
    });

</script>
</body>
</html>