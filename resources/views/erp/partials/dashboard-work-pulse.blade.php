<section class="erp-work-pulse" aria-label="Your current work priorities">
  <div class="erp-work-pulse__head">
    <div>
      <p class="erp-work-pulse__eyebrow">✦ YOUR WORKDAY · {{ strtoupper($actor->role) }}</p>
      <h2 class="erp-work-pulse__heading">{{ $actor->role === 'student' ? 'My work, one clear view.' : 'Your decisions, under control.' }}</h2>
      <p class="erp-work-pulse__subtitle">{{ $actor->role === 'student' ? 'See what is in progress, what needs your attention, and where to continue.' : 'Live, permission-scoped tasks from your authorized practice workspace.' }}</p>
    </div>
    <div class="erp-work-pulse__signal" aria-live="polite">
      @if($workPulse['attention_count'] > 0)
        <span class="erp-work-pulse__signal-dot"></span>
        <span>{{ $workPulse['attention_count'] }} {{ $actor->role === 'student' ? 'pending items' : 'items awaiting review' }}</span>
      @else
        <i data-lucide="check-check" aria-hidden="true"></i>
        <span>No outstanding review items</span>
      @endif
    </div>
  </div>
  <div class="erp-work-pulse__grid">
    @foreach($workPulse['items'] as $task)
    <a href="{{ route($task['route'],['firmSlug'=>$firmSlug]) }}"
      class="erp-work-pulse__item erp-work-pulse__item--{{ $task['tone'] }}"
      aria-label="{{ $task['title'] }}{{ $task['count'] ? ', '.$task['count'].' items' : '' }}">
      <div class="erp-work-pulse__item-top">
        <span class="erp-work-pulse__icon"><i data-lucide="{{ $task['icon'] }}" aria-hidden="true"></i></span>
        @if($task['count'] > 0)
          <span class="erp-work-pulse__count">{{ $task['count'] }} {{ $task['tone'] === 'live' ? 'open' : ($actor->role === 'student' ? 'in progress' : 'pending') }}</span>
        @else
          <i data-lucide="arrow-up-right" class="erp-work-pulse__arrow" aria-hidden="true"></i>
        @endif
      </div>
      <strong>{{ $task['title'] }}</strong>
      <span class="erp-work-pulse__desc">{{ $task['description'] }}</span>
    </a>
    @endforeach
  </div>
  <p class="erp-work-pulse__foot"><i data-lucide="lock-keyhole" aria-hidden="true"></i> Real records only · {{ $workspace->name }} · {{ $company->timezone }} · No automatic absence or salary penalty</p>
</section>
