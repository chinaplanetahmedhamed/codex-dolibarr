import { Link } from 'expo-router';
import { Pressable, StyleSheet, Text } from 'react-native';

import { AppScreen, BrandHeader, Hero, ProgressBar } from '@/src/components';
import { EmptyState, ErrorState, LoadingState } from '@/src/components/AsyncState';
import { CardHeader, DataCard, DetailRow, formatDate } from '@/src/components/DataCard';
import { PaginationFooter } from '@/src/components/PaginationFooter';
import { usePaginatedResource } from '@/src/hooks/usePaginatedResource';
import { useI18n } from '@/src/i18n/I18nProvider';
import { mobileRepository } from '@/src/repositories/mobileRepository';
import { palette } from '@/src/theme';

export default function ProjectsScreen() {
  const { language, label } = useI18n();
  const resource = usePaginatedResource((cursor) => mobileRepository.projects({ limit: 25, cursor }));
  if (resource.loading) return <AppScreen><BrandHeader titleAr="المشاريع" titleEn="Projects" /><LoadingState /></AppScreen>;
  if (resource.error) return <AppScreen><BrandHeader titleAr="المشاريع" titleEn="Projects" /><ErrorState error={resource.error} retry={resource.retry} /></AppScreen>;
  const projects = resource.items;
  return <AppScreen refreshing={resource.refreshing} onRefresh={resource.refresh}><BrandHeader titleAr="المشاريع" titleEn="My Projects" /><Hero eyebrow="PROJECTS" title={language === 'ar' ? 'مشاريعك' : 'Your projects'} subtitle={language === 'ar' ? 'الحالة والتقدم والخطوات والمواعيد.' : 'Status, progress, actions and dates.'} icon="folder-open" />{projects.length === 0 ? <EmptyState /> : projects.map((project) => <Link key={project.id} href={{ pathname: '/project/[id]', params: { id: project.id } }} asChild><Pressable><DataCard><CardHeader title={`${project.ref} · ${project.title}`} status={project.status} /><ProgressBar value={project.progress_percent} /><DetailRow label={language === 'ar' ? 'التقدم' : 'Progress'} value={`${project.progress_percent}%`} /><DetailRow label={language === 'ar' ? 'الخطوة الحالية' : 'Current step'} value={label(project.current_step) || '—'} /><DetailRow label={language === 'ar' ? 'الموعد المتوقع' : 'Expected date'} value={formatDate(project.expected_next_at, language)} /><Text style={styles.open}>{language === 'ar' ? 'فتح تفاصيل المشروع' : 'Open project details'}</Text></DataCard></Pressable></Link>)}<PaginationFooter hasMore={resource.hasMore} loading={resource.loadingMore} onPress={resource.loadMore} /></AppScreen>;
}

const styles = StyleSheet.create({ open: { color: palette.blue, fontSize: 11, fontWeight: '900', textAlign: 'center' } });
