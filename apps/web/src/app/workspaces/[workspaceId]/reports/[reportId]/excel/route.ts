import { reportExcelFilename, reportSnapshotExcelXml } from "@/lib/report-excel";
import { getWorkspaceReportSnapshot } from "@/lib/workspace-data";

type ReportExcelRouteProps = {
  params: Promise<{
    workspaceId: string;
    reportId: string;
  }>;
};

function contentDisposition(filename: string) {
  return `attachment; filename*=UTF-8''${encodeURIComponent(filename)}`;
}

export async function GET(_request: Request, { params }: ReportExcelRouteProps) {
  const { workspaceId, reportId } = await params;
  const document = await getWorkspaceReportSnapshot(workspaceId, reportId);

  if (!document) {
    return new Response("Report not found", { status: 404 });
  }

  return new Response(reportSnapshotExcelXml(document), {
    headers: {
      "Content-Disposition": contentDisposition(reportExcelFilename(document.report.title)),
      "Content-Type": "application/vnd.ms-excel; charset=utf-8"
    }
  });
}
