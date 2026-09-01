import { useLocalSearchParams } from 'expo-router';
import { useRef } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

import { AppScreen, BrandHeader, ProgressBar, SectionHeader } from '@/src/components';
import { EmptyState, ErrorState, LoadingState } from '@/src/components/AsyncState';
import { DataCard, DetailRow, formatDate } from '@/src/components/DataCard';
import { DownloadButton } from '@/src/components/DownloadButton';
import { QcEvidenceItem } from '@/src/components/QcEvidenceItem';
import { useResource } from '@/src/hooks/useResource';
import { useI18n } from '@/src/i18n/I18nProvider';
import { mobileRepository } from '@/src/repositories/mobileRepository';
import { palette } from '@/src/theme';
import { ReportDetail } from '@/src/api/types';

export default function ReportDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>(); const reportId = Array.isArray(id) ? id[0] : id;
  const { language, label } = useI18n(); const resource = useResource(() => mobileRepository.report(reportId), [reportId]);
  if (resource.loading) return <AppScreen><LoadingState /></AppScreen>; if (resource.error) return <AppScreen><ErrorState error={resource.error} retry={resource.retry} /></AppScreen>; const report = resource.data; if (!report) return <AppScreen><EmptyState /></AppScreen>;
  if (report.report_type === 'quality_control' && report.sections && report.summary && report.inspection) return <LocalizedQcReport language={language} onRefresh={resource.refresh} refreshing={resource.refreshing} report={report} />;
  return <AppScreen refreshing={resource.refreshing} onRefresh={resource.refresh}><BrandHeader titleAr="التقرير" titleEn="Report" /><DataCard><DetailRow label={report.ref} value={report.title} /><DetailRow label={language === 'ar' ? 'المرحلة' : 'Stage'} value={label(report.stage.label)} /><ProgressBar value={report.progress_percent} /><DetailRow label={language === 'ar' ? 'التقدم' : 'Progress'} value={`${report.progress_percent}%`} /><DetailRow label={language === 'ar' ? 'آخر تحديث' : 'Updated'} value={formatDate(report.updated_at, language)} />{report.public_note && <Text>{report.public_note}</Text>}</DataCard><SectionHeader ar="حقول التقرير" en="Report fields" />{report.fields.length === 0 ? <EmptyState /> : report.fields.map((field) => <DataCard key={field.code}><DetailRow label={label(field.label)} value={field.value} /></DataCard>)}<SectionHeader ar="ملفات التقرير" en="Report documents" />{report.documents.length === 0 ? <EmptyState /> : report.documents.map((document) => <DataCard key={document.id}><DetailRow label={document.name} value={document.mime_type} /><DownloadButton target={{ kind: 'report-document', reportId: report.id, documentId: document.id }} filename={document.name} /></DataCard>)}</AppScreen>;
}

function LocalizedQcReport({ report, language, refreshing, onRefresh }: { report: ReportDetail; language: 'ar' | 'en'; refreshing: boolean; onRefresh: () => void }) {
  const ar = language === 'ar'; const failures = report.sections?.flatMap((section) => section.checkpoints.filter((checkpoint) => checkpoint.result === 'fail').map((checkpoint)=>({checkpoint,sectionCode:section.code}))) ?? [];
  const scrollRef=useRef<ScrollView>(null),sectionOffsets=useRef<Record<string,number>>({}),checkpointOffsets=useRef<Record<string,number>>({});const jump=(id:string,sectionCode:string)=>scrollRef.current?.scrollTo({y:Math.max(0,(sectionOffsets.current[sectionCode]??0)+(checkpointOffsets.current[id]??0)-16),animated:true});
  return <AppScreen refreshing={refreshing} onRefresh={onRefresh} scrollRef={scrollRef}>
    <BrandHeader titleAr="تقرير فحص الجودة" titleEn="QC Report" />
    <DataCard>
      <DetailRow label={report.ref} value={report.title} />
      <Text accessibilityLabel={`${ar ? 'النتيجة' : 'Result'}: ${resultLabel(report.overall_result, language)}`} accessibilityRole="text" style={[styles.result, report.overall_result === 'pass' ? styles.pass : report.overall_result === 'fail' ? styles.fail : styles.pending]}>{resultIcon(report.overall_result)} {resultLabel(report.overall_result, language)}</Text>
      <DetailRow label={ar ? 'الإصدار' : 'Version'} value={`v${report.inspection?.version ?? 1}`} />
      <DetailRow label={ar ? 'تاريخ الفحص' : 'Inspected'} value={formatDate(report.inspection?.inspected_at, language)} />
      <DetailRow label={ar ? 'تاريخ الاعتماد' : 'Published'} value={formatDate(report.inspection?.published_at, language)} />
      {report.public_note ? <Text>{report.public_note}</Text> : null}
      {report.pdf ? <DownloadButton target={{ kind: 'qc-pdf', reportId: report.id, publicationId: report.pdf.id }} filename={report.pdf.name} /> : null}
    </DataCard>
    {failures.length > 0 ? <DataCard><Text style={styles.failureTitle}>{ar ? `نقاط الفشل (${failures.length})` : `Failed checkpoints (${failures.length})`}</Text>{failures.map(({checkpoint,sectionCode}) => <Pressable accessibilityHint={ar?'ينقلك إلى تفاصيل نقطة الفشل':'Moves to the failed checkpoint details'} accessibilityRole="button" key={checkpoint.id} onPress={()=>jump(checkpoint.id,sectionCode)} style={styles.failureLink}><Text style={styles.failureLinkText}>✕ {checkpoint.requirement}</Text></Pressable>)}</DataCard> : null}
    <SectionHeader ar="ملخص النتائج" en="Result summary" />
    <View accessibilityLabel={ar ? 'أعداد نقاط الفحص' : 'Checkpoint counts'} style={styles.counts}>
      <Count label={checkpointLabel('pass', language)} value={report.summary?.pass ?? 0} /><Count label={checkpointLabel('fail', language)} value={report.summary?.fail ?? 0} /><Count label={checkpointLabel('not_applicable', language)} value={report.summary?.not_applicable ?? 0} /><Count label={severityLabel('critical', language)} value={report.summary?.critical ?? 0} /><Count label={severityLabel('major', language)} value={report.summary?.major ?? 0} /><Count label={severityLabel('minor', language)} value={report.summary?.minor ?? 0} />
    </View>
    {report.sections?.map((section) => <View key={section.code} onLayout={(event)=>{sectionOffsets.current[section.code]=event.nativeEvent.layout.y;}}><SectionHeader ar={section.title} en={section.title} />{section.checkpoints.map((checkpoint) => <View key={checkpoint.id} nativeID={`qc-checkpoint-${checkpoint.id}`} onLayout={(event)=>{checkpointOffsets.current[checkpoint.id]=event.nativeEvent.layout.y;}}><DataCard>
      <View style={styles.checkpointHead}><Text style={styles.requirement}>{checkpoint.requirement}</Text><Text accessibilityLabel={`${ar ? 'النتيجة' : 'Result'}: ${checkpointLabel(checkpoint.result, language)}`} style={[styles.badge, checkpoint.result === 'pass' ? styles.pass : checkpoint.result === 'fail' ? styles.fail : styles.pending]}>{resultIcon(checkpoint.result)} {checkpointLabel(checkpoint.result, language)}</Text></View>
      <DetailRow label={ar ? 'المطلوب' : 'Expected'} value={checkpoint.expected_value || '—'} /><DetailRow label={ar ? 'التفاوت' : 'Tolerance'} value={checkpoint.tolerance || '—'} /><DetailRow label={ar ? 'النتيجة الفعلية' : 'Actual'} value={checkpoint.observation || '—'} /><DetailRow label={ar ? 'شدة العيب' : 'Severity'} value={severityLabel(checkpoint.severity, language)} />
      {checkpoint.public_note ? <Text>{checkpoint.public_note}</Text> : null}{checkpoint.evidence.map((evidence) => <QcEvidenceItem evidence={evidence} key={evidence.id} reportId={report.id} />)}
    </DataCard></View>)}</View>)}
  </AppScreen>;
}

function checkpointLabel(value: string, language: 'ar' | 'en') { const labels: Record<string,[string,string]>={pass:['ناجح','Pass'],fail:['راسب','Fail'],not_applicable:['لا ينطبق','N/A'],pending:['معلّق','Pending']}; const label=labels[value]||[value,value]; return label[language==='ar'?0:1]; }
function severityLabel(value: string, language: 'ar' | 'en') { const labels: Record<string,[string,string]>={none:['بدون','None'],critical:['حرج','Critical'],major:['رئيسي','Major'],minor:['بسيط','Minor']}; const label=labels[value]||[value,value]; return label[language==='ar'?0:1]; }
function resultIcon(result?: string){return result==='pass'?'✓':result==='fail'?'✕':result==='not_applicable'?'—':'!';}

function resultLabel(result: string | undefined, language: 'ar' | 'en') { const labels: Record<string, [string,string]> = { pass: ['ناجح','Pass'], fail: ['راسب','Fail'], pending_corrective_action: ['بانتظار الإجراء التصحيحي','Pending corrective action'], reinspection_required: ['إعادة فحص مطلوبة','Reinspection required'] }; const value=labels[result || ''] || ['قيد المراجعة','Pending']; return language === 'ar' ? value[0] : value[1]; }
function Count({ label, value }: { label: string; value: number }) { return <View style={styles.count}><Text style={styles.countLabel}>{label}</Text><Text style={styles.countValue}>{value}</Text></View>; }
const styles=StyleSheet.create({result:{alignSelf:'flex-start',paddingHorizontal:12,paddingVertical:7,borderRadius:999,fontWeight:'900'},pass:{backgroundColor:palette.greenSoft,color:palette.green},fail:{backgroundColor:palette.redSoft,color:palette.red},pending:{backgroundColor:palette.orangeSoft,color:palette.orange},counts:{flexDirection:'row',flexWrap:'wrap',gap:8},count:{minWidth:92,flexGrow:1,backgroundColor:palette.surface,borderWidth:1,borderColor:palette.border,borderRadius:12,padding:12},countLabel:{color:palette.muted,fontSize:11,fontWeight:'700'},countValue:{color:palette.navy,fontSize:19,fontWeight:'900'},checkpointHead:{gap:8},requirement:{color:palette.navy,fontSize:16,lineHeight:23,fontWeight:'900'},badge:{alignSelf:'flex-start',paddingHorizontal:9,paddingVertical:5,borderRadius:999,fontSize:10,fontWeight:'900'},failureTitle:{color:palette.red,fontWeight:'900'},failureLink:{minHeight:44,justifyContent:'center',paddingVertical:8},failureLinkText:{color:palette.red,fontWeight:'800',textDecorationLine:'underline'}});
