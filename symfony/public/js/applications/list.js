$(document).ready(function() {
  //Установка параметра количества записей на одной странице
  $('#paginationResultsCount').change(function() {
    $.post('/applications/set-results-count', {results: $(this).val()}).done(function(data) {location.reload();});
  });

  //Фильтрация
  $('#filterBtn').click(function() {
    var form = $('#filterForm');
    var data = {};
    dataArray = form.serializeArray();
    dataArray.forEach(function(element) {data[element.name] = element.value;});
    freezeForm(form);
    $.redirectPost(form.attr('action'), data);
  });

  //Сортировка
  $('.app-sort').click(function() {
    var sortOrderBy = $(this).data('order');
    var sort = $(this).data('sort');
    var form = $('#filterForm');
    form.find('input[name="filterOrderBy"]').val(sortOrderBy);
    form.find('input[name="filterSort"]').val(sort);
    form.submit();
  });

  //Постраничная навигация
  $('.page-item a').click(function(event) {
    event.preventDefault();
    var page = $(this).data('page');
    var form = $('#filterForm');
    form.find('input[name="filterPage"]').val(page);
    form.submit();
  });

  //Инициализация fileinput для предпросмотра файлов
  $('#attachments').fileinput(params);
  $('.filePreview').click(function(event) {
    event.preventDefault();
    var previewId = $(this).data('id');
    $('#attachments').fileinput('zoom', previewId);
  });

  //Разворачивание документов
  $('.show-hidden-buttons').click(function() {
    $(this).addClass('d-none');
    $(this).closest('td').find('.d-none').not('.show-hidden-buttons').removeClass('d-none');
  });

  //Фикс дропдауна при просмотре счета (когда он ниже таблицы с overflow-scroll)
  $('.table-responsive').on('shown.bs.dropdown', function(event) {
    var table = $(this).find('table');
    var dropdown = $('#' + event.target.id).parent().find('ul');
    var mb = dropdown.offset().top - table.offset().top + dropdown.outerHeight() - table.height() + 40;
    if (mb > 0 && mb > parseInt($('.table-bordered').css('margin-bottom').replace('px', ''), 10)) {table.attr('style', 'margin-bottom: ' + mb + 'px !important');}
  });

  $('.table-responsive').on('hidden.bs.dropdown', function(event) {
    var table = $(this).find('table');
    table.attr('style', '');
  });
});