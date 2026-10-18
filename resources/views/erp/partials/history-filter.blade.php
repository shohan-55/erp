<div class="erp-history-tools" data-corporate-history-toolbar data-history-target="{{ $historyKey }}">
  <div>
    <strong>{{ $historyTitle }}</strong>
    <p>Filter records already loaded on this page. Existing approvals, receipts and dates remain unchanged.</p>
  </div>
  <div class="erp-history-tools__fields">
    <label class="sr-only" for="history-search-{{ $historyKey }}">Search {{ $historyTitle }}</label>
    <input id="history-search-{{ $historyKey }}" type="search" autocomplete="off"
      placeholder="Search member, date, reason…" data-history-search class="erp-history-tools__search">
    <label class="sr-only" for="history-status-{{ $historyKey }}">Filter {{ $historyTitle }} by status</label>
    <select id="history-status-{{ $historyKey }}" data-history-status class="erp-history-tools__status">
      <option value="all">All statuses</option>
      @foreach($historyStatuses as $status=>$label)
        <option value="{{ $status }}">{{ $label }}</option>
      @endforeach
    </select>
    <button type="button" data-history-reset class="erp-history-tools__reset">Reset</button>
  </div>
  <span class="erp-history-tools__result" data-history-count role="status" aria-live="polite"></span>
</div>
