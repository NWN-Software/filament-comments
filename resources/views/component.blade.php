<livewire:comments 
    :record="$record ?? $this->record" 
    :resource="$resource" 
    :sendMailWhenTagged="$sendMailWhenTagged ?? false"
    :mailSubjectForTaggedUsers="$mailSubjectForTaggedUsers ?? ''"
/>