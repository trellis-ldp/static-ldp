<?php

namespace Trellis\StaticLdp;

class TrellisConstants
{

    const CONSTRAINED_BY = "http://www.w3.org/ns/ldp#constrainedBy";
    const READ_ONLY_RESOURCE = "http://acdc.amherst.edu/ns/trellis#ReadOnlyResource";

    const READ_ONLY_RESOURCE_LINK = "<" . TrellisConstants::READ_ONLY_RESOURCE . ">; rel=\"" .
      TrellisConstants::CONSTRAINED_BY . "\"";
}
