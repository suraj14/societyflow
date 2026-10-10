/// One entry in the user's bell (the same list the web header shows). Only
/// ever the signed-in user's own.
class AppNotification {
  final String id;
  final String category;
  final String title;
  final String body;
  final bool read;
  final String timeAgo;

  const AppNotification({
    required this.id,
    required this.category,
    required this.title,
    required this.body,
    required this.read,
    required this.timeAgo,
  });

  factory AppNotification.fromJson(Map<String, dynamic> json) => AppNotification(
        id: json['id']?.toString() ?? '',
        category: json['category']?.toString() ?? 'general',
        title: json['title']?.toString() ?? 'Notification',
        body: json['body']?.toString() ?? '',
        read: json['read'] == true,
        timeAgo: json['time_ago']?.toString() ?? '',
      );

  AppNotification asRead() => AppNotification(id: id, category: category, title: title, body: body, read: true, timeAgo: timeAgo);
}
