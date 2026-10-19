import { Badge } from "@/components/ui/Badge";
import { statusTone } from "@/lib/labels";
import type { TransferStatus } from "@/lib/transfers";

export function StatusBadge({ status, label, className }: { status: TransferStatus; label: string; className?: string }) {
  return <Badge tone={statusTone(status)} className={className}>{label}</Badge>;
}
