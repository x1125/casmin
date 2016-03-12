$(function(){

    $(document).on('click', '.processModalButton', function(e){
        e.preventDefault();

        var target = $(this).attr('data-target');
        var modal = $(this).parents('.modal');
        var modalStatus = modal.find('.modal-body > .status');
        var formData = modal.find('form').serialize();

        modal.find('.btn').addClass('disabled');

        $.post(target, formData, function(response){
            modal.find('.btn').removeClass('disabled');

            if (!('status' in response))
            {
                alert('Invalid response');
                console.log(response);
                return;
            }

            modalStatus.html('<div class="alert alert-' + (response.status ? 'success' : 'danger') + '" role="alert">' + (response.message ? response.message : 'Success!') + '</div>');
            if (response.status)
                window.location.reload();
        });
    });

    $(document).on('click', '.processQueryModalButton', function(e){
        e.preventDefault();

        var target = $(this).attr('data-target');
        var modal = $(this).parents('.modal');
        var modalStatus = modal.find('.modal-body > .status');
        var formData = modal.find('form').serialize();

        modal.find('.btn').addClass('disabled');

        $.post(target, formData, function(response){
            modal.find('.btn').removeClass('disabled');

            // no status given? something really went wrong
            if (!('status' in response))
            {
                alert('Invalid response');
                console.log(response);
                return;
            }

            // some error occurred
            if (!response.status)
            {
                modalStatus.html('<div class="alert alert-danger" role="alert">' + response.message + '</div>');
                return;
            }

            // preview query
            if (response.status)
            {
                var queryModal = $('#executeQueryModal');
                queryModal.find('.modal-body pre.query').text(response.query);
                queryModal.find('.modal-body div.alert').remove();
                queryModal.modal('show');
            }
        });
    });

    $(document).on('click', '.executeQueryModalButton', function(e){
        e.preventDefault();

        var queryModal = $('#executeQueryModal');
        var query = queryModal.find('.modal-body pre.query').text();
        var postData = {
            'cluster': cluster,
            'keyspace': keyspace,
            'query': query
        };

        queryModal.find('.btn').addClass('disabled');
        $.post(queryModal.attr('data-target'), postData, function(response){
            queryModal.find('.btn').removeClass('disabled');

            // no status given? something really went wrong
            if (!('status' in response))
            {
                alert('Invalid response');
                console.log(response);
                return;
            }

            // remove previous alerts
            queryModal.find('.modal-body div.alert').remove();

            // some error occurred
            if (!response.status || !response.ret)
            {
                queryModal.find('.modal-body pre.query').after('<div class="alert alert-danger" role="alert">' + response.message + '</div>');
                return;
            }

            // preview query
            if (response.status)
            {
                queryModal.find('.modal-body pre.query').after('<div class="alert alert-success" role="alert">Success!</div>');
                window.location.reload();
            }
        });
    });

    var replicationsCount = 1;
    $(document).on('click', '.addReplicationRow', function(e){
        e.preventDefault();

        var listGroup = $(this).siblings('.list-group');
        var dummy = listGroup.children().first().clone();

        dummy
            .find('.removeReplicationRow')
            .removeClass('hide');

        dummy
            .attr('data-count', replicationsCount);

        dummy
            .find('.replicationDatacenter')
            .attr('name', 'replication[' + replicationsCount + '][datacenter]')
            .val('');

        dummy
            .find('.replicationFactor')
            .attr('name', 'replication[' + replicationsCount + '][factor]')
            .val('');

        listGroup.append(dummy);

        replicationsCount++;
    });

    $(document).on('click', '.removeReplicationRow', function(e){
        e.preventDefault();

        var groupItem = $(this).parents('li.list-group-item');

        if (groupItem.attr('data-count') > 0)
            groupItem.remove();
    });

    $(document).on('change', '#addKeyspaceModal #class', function(){
        $('.VariableKeyspaceInput').addClass('hide');
        $('.' + $(this).val() + 'Input').removeClass('hide');
    });

    $(document).on('click', '.removeModalProcess', function(e){
        e.preventDefault();

        var removeWarningDialog = $('#removeWarningDialog');

        removeWarningDialog
            .find('a.proceed')
            .attr('href', $(this).attr('href'));

        removeWarningDialog
            .modal('show');
    });

    var columnFamilyFieldId = 1;
    $(document).on('click', '.addColumnFamilyField', function(e){
        e.preventDefault();

        // clone the dummy
        var dummy = $('#columnFamilyFieldDummyRow').clone();

        // add unique id to dummy row
        dummy
            .removeAttr('id')
            .attr('data-id', columnFamilyFieldId);

        // add unique id to all inputs
        dummy.find('input,select').each(function(){
            $(this).attr('name', $(this).attr('name') + '[' + columnFamilyFieldId + ']');
        });

        // check if only using alter (disable pkey and skey)
        if ($(this).hasClass('alterOnly'))
        {
            dummy.find('input[type="radio"]').each(function(){
                if ($(this).val() == 'primary' || $(this).val() == 'secondary')
                    $(this).attr('disabled', 'disabled');
            });
        }

        // append dummy to table
        $('#columnFamilyFieldTable tbody').append(dummy);

        // increment counter
        columnFamilyFieldId++;
    });

    $(document).on('click', '.removeColumnFamilyField', function(e){
        e.preventDefault();

        $(this).parents('tr').remove();
        updateClusteringOrderItems();
    });

    function getDummyFieldClone(dummyField, index)
    {
        var name = dummyField.attr('name') + '[' + dummyField.val() + ']';
        if (index != -1)
            name += '[' + index + ']';

        return dummyField.clone().attr('name', name);
    }

    $(document).on('change', '#columnFamilyFieldTable select.fieldTypeSelector', function(e){
        e.preventDefault();

        var type = $(this).val();
        var addFields = 0;

        if (type == 'set' || type == 'list' || type == 'tuple')
            addFields = 1;
        else if (type == 'map')
            addFields = 2;

        // clean up the old ones
        var next = $(this).next();
        if (next.length > 0 && next.hasClass('container-fluid'))
            next.remove();

        // early exit
        if (addFields == 0)
            return;

        // create container for some spacing
        var container = $('<div class="container-fluid" />');

        // add fields to container
        if (addFields == 1)
            container.append(getDummyFieldClone($(this), (type == 'tuple' ? 0 : -1)));
        else if(addFields > 1)
        {
            container.append($('<div class="row" />')
                .append($('<div class="col-md-6" />').append(getDummyFieldClone($(this), 0)))
                .append($('<div class="col-md-6" />').append(getDummyFieldClone($(this), 1)))
            );
        }

        // add "add"-button
        if (type == 'tuple')
            container.append('<a href="#" class="btn btn-xs btn-success addDynamicFieldType"><span class="glyphicon glyphicon-plus" aria-hidden="true"></span></a>');

        // display everything
        $(this).after(container);
    });

    $(document).on('click', '.addDynamicFieldType', function(e){
        e.preventDefault();

        var clone = $(this).prev().clone();
        var name = clone.attr('name');
        // woahaha, rather complex
        var id = parseInt(name.substr(name.lastIndexOf('[') + 1, name.length - name.lastIndexOf('[') - 2));

        var newName = name.substr(0, name.lastIndexOf('[')) + '[' + (id + 1) + ']';

        clone.attr('name', newName);
        $(this).before(clone);
    });

    $(document).on('click', '.moveFieldRow', function(e){
        e.preventDefault();

        // get the row we want to move
        var row = $(this).parents('tr[data-id]');

        // get the target row
        var targetSibling;
        if ($(this).attr('data-direction') == 'up')
            targetSibling = row.prev();
        else
            targetSibling = row.next();

        // early exit
        if (targetSibling.length < 1)
            return;

        // switch position
        if ($(this).attr('data-direction') == 'up')
            targetSibling.before(row);
        else
            targetSibling.after(row);

        // update names
        var rowId = row.attr('data-id');
        var targetSiblingId = targetSibling.attr('data-id');

        // update row's input names, using a prefix
        row.find('input,select').each(function(){
            $(this).attr('name', 'x' + $(this).attr('name').replace('[' + rowId + ']', '[' + targetSiblingId + ']'));
        });

        // update targetSibling's input names
        targetSibling.find('input,select').each(function(){
            $(this).attr('name', $(this).attr('name').replace('[' + targetSiblingId + ']', '[' + rowId + ']'));
        });

        // remove the prefix again (prevent forgetting the radio selection)
        row.find('input,select').each(function(){
            $(this).attr('name', $(this).attr('name').substr(1));
        });

        // update the ids
        row.attr('data-id', targetSiblingId);
        targetSibling.attr('data-id', rowId);
    });

    $(document).on('change', '.fieldName', function(){
        updateClusteringOrderItems();
    });

    function updateClusteringOrderItems()
    {
        $('#clustering_order option:not([data-keep])').remove();

        $('#columnFamilyFieldTable .fieldName').each(function(){
            var name = $(this).val();

            if (name.length > 0)
            {
                $('#clustering_order').append(
                    $('<option />')
                        .attr('value', $(this).val())
                        .html($(this).val())
                );
            }
        });
    }

    $(document).on('change', 'select[name="caching_rows_per_partition"]', function(){
        var input = $('input[name="rows_per_partition_num"]');
        if ($(this).val() == 'number')
            input.removeAttr('disabled');
        else
            input.attr('disabled', '');
    });

    $(document).on('change', 'select[name="speculative_retry"]', function(){
        var input = $('input[name="speculative_retry_value"]');

        if ($(this).val() == 'Xpercentile' || $(this).val() == 'Yms')
        {
            input
                .removeAttr('disabled')
                .next().html($(this).val() == 'Xpercentile' ? '%' : 'ms');
        }
        else
            input.attr('disabled', '');
    });

    $('select#compaction').change(function(){
        $('.ColumnFamilyCompaction').addClass('hide');
        $('.ColumnFamilyCompaction[data-type="' + $(this).val() + '"]').removeClass('hide');
    });

    $('#addColumnFamilyModal').on('show.bs.modal', function (e) {
        var input = $('#speculative_retry_value');
        if (!input.parent().hasClass('input-group'))
        {
            input.wrap($('<div class="input-group" />'));
            input.after('<div class="input-group-addon">%</div>');
        }
    });

    $('#executeCommand').on('click', function(e){
        e.preventDefault();

        var fieldset = $(this).parents('fieldset');
        var postData = {
            'command': $('#command').val(),
            'cluster': $('#cluster').val()
        };

        fieldset.attr('disabled', 'disabled');
        $.post($(this).attr('href'), postData, function(response){
            fieldset.removeAttr('disabled');

            // no status given? something really went wrong
            if (!('status' in response))
            {
                alert('Invalid response');
                console.log(response);
                return;
            }

            // remove previous alerts
            $('#executeOutput').parent().find('div.alert').remove();

            // some error occurred
            if (!response.status || !response.output)
            {
                $('#executeOutput').after('<div class="alert alert-danger" role="alert">' + response.message + '</div>');
                return;
            }

            // preview query
            if (response.status)
            {
                $('#executeOutput').html(response.output);
            }
        });
    });

});