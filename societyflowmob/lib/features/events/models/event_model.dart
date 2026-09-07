class EventModel {
  final int id;
  final String title;
  final String? description;
  final String? eventDate;
  final String? startTime;
  final String? endTime;
  final String? venue;
  final String status;
  final String? image;
  final DateTime createdAt;

  const EventModel({
    required this.id, required this.title, this.description,
    this.eventDate, this.startTime, this.endTime, this.venue,
    required this.status, this.image, required this.createdAt,
  });

  factory EventModel.fromJson(Map<String, dynamic> json) => EventModel(
    id:          json['id'] as int,
    title:       json['title']?.toString() ?? '',
    description: json['description']?.toString(),
    eventDate:   json['event_date']?.toString(),
    startTime:   json['start_time']?.toString(),
    endTime:     json['end_time']?.toString(),
    venue:       json['venue']?.toString(),
    status:      json['status']?.toString() ?? 'upcoming',
    image:       json['image']?.toString(),
    createdAt:   DateTime.parse(json['created_at'].toString()),
  );

  bool get isUpcoming  => status == 'upcoming';
  bool get isOngoing   => status == 'ongoing';
  bool get isCompleted => status == 'completed';

  String get statusLabel {
    switch (status) {
      case 'upcoming': return 'Upcoming';
      case 'ongoing':  return 'Ongoing';
      case 'completed': return 'Completed';
      default: return status;
    }
  }
}