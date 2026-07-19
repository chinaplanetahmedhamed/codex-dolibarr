import { Redirect, useLocalSearchParams } from 'expo-router';

export default function LegacyReportRedirect() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const reportId = Array.isArray(id) ? id[0] : id;
  return <Redirect href={{ pathname: '/more/report/[id]', params: { id: reportId } }} />;
}
